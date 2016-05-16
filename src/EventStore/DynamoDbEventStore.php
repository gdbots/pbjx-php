<?php

namespace Gdbots\Pbjx\EventStore;

use Aws\CommandPool;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\WriteRequestBatch;
use Aws\Exception\AwsException;
use Gdbots\Common\Microtime;
use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\Marshaler\DynamoDb\ItemMarshaler;
use Gdbots\Pbjx\Exception\EventStoreOperationFailed;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\OptimisticCheckFailed;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DynamoDbEventStore implements EventStore
{
    /** @var Pbjx */
    protected $pbjx;

    /** @var DynamoDbClient */
    protected $client;

    /**
     * The name of the DynamoDb table to write to.  This is the default value
     * and can change based on hints provided.
     *
     * @var string
     */
    protected $tableName;

    /** @var LoggerInterface */
    protected $logger;

    /** @var ItemMarshaler */
    protected $marshaler;

    /**
     * @param Pbjx $pbjx
     * @param DynamoDbClient $client
     * @param string $tableName
     * @param LoggerInterface|null $logger
     */
    public function __construct(Pbjx $pbjx, DynamoDbClient $client, $tableName, LoggerInterface $logger = null)
    {
        $this->pbjx = $pbjx;
        $this->client = $client;
        $this->tableName = $tableName;
        $this->logger = $logger ?: new NullLogger();
        $this->marshaler = new ItemMarshaler();
    }

    /**
     * {@inheritdoc}
     */
    final public function putEvents($streamId, array $events, array $hints = [], $expectedEtag = null)
    {
        if (!count($events)) {
            // ignore empty events array
            return;
        }

        if (null !== $expectedEtag) {
            $this->optimisticCheck($streamId, $hints, $expectedEtag);
        }

        $tableName = $this->determineTableNameForWrite($hints);
        $batch = new WriteRequestBatch($this->client, [
            'table' => $tableName,
            'autoflush' => false,
            'error' => function(AwsException $e) use ($streamId, $tableName) {
                throw new EventStoreOperationFailed(
                    sprintf(
                        'Failed to put some or all events into DynamoDb table [%s] for stream [%s] with message: %s',
                        $tableName,
                        $streamId,
                        ClassUtils::getShortName($e) . '::' . $e->getMessage()
                    ),
                    Code::DATA_LOSS,
                    $e
                );
            }
        ]);

        /** @var Event[] $events */
        foreach ($events as $event) {
            if (!$event->isFrozen()) {
                $this->pbjx->triggerLifecycle($event);
            }

            $item = $this->marshaler->marshal($event);
            $item[DynamoDbEventStoreTable::HASH_KEY_NAME] = ['S' => $streamId];
            $this->beforePutItem($event, $item);
            $batch->put($item);
        }

        $batch->flush();
    }

    /**
     * {@inheritdoc}
     */
    final public function getEvents($streamId, Microtime $since = null, $count = 25, $forward = true, array $hints = [])
    {
        $tableName = $this->determineTableNameForRead($hints);
        $consistentRead = isset($hints['consistent_read']) ? filter_var($hints['consistent_read'], FILTER_VALIDATE_BOOLEAN) : false;
        $count = NumberUtils::bound($count, 1, 100);

        if ($forward) {
            $since = null !== $since ? $since->toString() : '0';
        } else {
            $since = null !== $since ? $since->toString() : Microtime::create()->toString();
        }

        try {
            $response = $this->client->query([
                'TableName' => $tableName,
                'ExpressionAttributeNames' => [
                    '#HASH' => DynamoDbEventStoreTable::HASH_KEY_NAME,
                    '#RANGE' => DynamoDbEventStoreTable::RANGE_KEY_NAME,
                ],
                'KeyConditionExpression' => sprintf('#HASH = :v_id AND #RANGE %s :v_date', $forward ? '>' : '<'),
                'ExpressionAttributeValues' => [
                    ':v_id' => ['S' => $streamId],
                    ':v_date' => ['N' => $since]
                ],
                'ScanIndexForward' => $forward,
                'Limit' => $count,
                'ConsistentRead' => $consistentRead
            ]);

        } catch (AwsException $e) {
            if ('ProvisionedThroughputExceededException' === $e->getAwsErrorCode()) {
                throw new EventStoreOperationFailed(
                    sprintf('Read provisioning exceeded on DynamoDb table [%s:%s].', $tableName, $streamId),
                    Code::RESOURCE_EXHAUSTED,
                    $e
                );
            }

            throw new EventStoreOperationFailed(
                sprintf(
                    'Failed to query events from DynamoDb table [%s] for stream [%s] with message: %s',
                    $tableName,
                    $streamId,
                    ClassUtils::getShortName($e) . '::' . $e->getMessage()
                ),
                Code::UNAVAILABLE,
                $e
            );

        } catch (\Exception $e) {
            throw new EventStoreOperationFailed(
                sprintf(
                    'Failed to query events from DynamoDb table [%s] for stream [%s] with message: %s',
                    $tableName,
                    $streamId,
                    ClassUtils::getShortName($e) . '::' . $e->getMessage()
                ),
                Code::INTERNAL,
                $e
            );
        }

        if (!$response['Count']) {
            return new EventCollection([], $streamId, $forward);
        }

        $events = [];
        foreach ($response['Items'] as $item) {
            try {
                $events[] = $this->unmarshalItem($item);
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf(
                        'Item returned from DynamoDb table [%s] from stream [%s] could not be unmarshaled. %s',
                        $tableName,
                        $streamId,
                        ClassUtils::getShortName($e) . '::' . $e->getMessage()
                    ),
                    [
                        'exception' => $e,
                        'item' => $item,
                        'hints' => $hints,
                    ]
                );
            }
        }

        return new EventCollection($events, $streamId, $forward, $response['Count'] >= $count);
    }

    /**
     * {@inheritdoc}
     */
    final public function streamEvents($streamId, Microtime $since = null, array $hints = [])
    {
        do {
            $collection = $this->getEvents($streamId, $since, 100, true, $hints);
            $since = $collection->getLastMicrotime();

            foreach ($collection as $event) {
                yield $event;
            }

        } while ($collection->hasMore());
    }

    /**
     * {@inheritdoc}
     */
    final public function streamAllEvents(\Closure $callback, Microtime $since = null, array $hints = [])
    {
        $tableName = $this->determineTableNameForRead($hints);
        $skipErrors = isset($hints['skip_errors']) ? filter_var($hints['skip_errors'], FILTER_VALIDATE_BOOLEAN) : false;
        $limit = isset($hints['limit']) ? $hints['limit'] : 2;
        $limit = NumberUtils::bound($limit, 1, 100);
        $totalSegments = isset($hints['total_segments']) ? $hints['total_segments'] : 5;
        $totalSegments = NumberUtils::bound($totalSegments, 1, 64);

        if (null !== $since) {
            $params = [
                'TableName' => $tableName,
                'ExpressionAttributeNames' => [
                    '#RANGE' => DynamoDbEventStoreTable::RANGE_KEY_NAME,
                ],
                'FilterExpression' => sprintf('#RANGE %s :v_date', '>'),
                'ExpressionAttributeValues' => [
                    ':v_date' => ['N' => $since->toString()]
                ],
                'Limit' => $limit,
                'TotalSegments' => $totalSegments
            ];
        } else {
            $params = ['TableName' => $tableName, 'Limit' => $limit, 'TotalSegments' => $totalSegments];
        }

        $commands = [];
        for ($segment = 0; $segment < $totalSegments; $segment++) {
            $params['Segment'] = $segment;
            $commands[] = $this->client->getCommand('Scan', $params);
        }

        $pool = new CommandPool($this->client, $commands, [
            'before' => function ($command, $key) {
                echo get_class($command).'=>'.$key.PHP_EOL;
            },
            'fulfilled' => function($result, $segmentKey, PromiseInterface $promise) use ($callback, $tableName, $hints) {
                foreach ($result['Items'] as $item) {
                    try {
                        $callback($this->unmarshalItem($item));
                    } catch (\Exception $e) {
                        $this->logger->error(
                            sprintf(
                                'Item returned from DynamoDb table [%s#segment=%s] from stream [%s] could not be unmarshaled. %s',
                                $tableName,
                                $segmentKey,
                                $item[DynamoDbEventStoreTable::HASH_KEY_NAME]['S'],
                                ClassUtils::getShortName($e) . '::' . $e->getMessage()
                            ),
                            [
                                'exception' => $e,
                                'item' => $item,
                                'hints' => $hints,
                                'segment' => $segmentKey
                            ]
                        );
                    }
                }
            },

            'rejected' => function ($exception, $segmentKey, PromiseInterface $promise) use ($tableName, $hints, $skipErrors) {
                echo $exception->getMessage().PHP_EOL;
                $msg = sprintf(
                    'Parallel scan failed on DynamoDb table [%s] on segment [%s].  Reason: %s',
                    $tableName,
                    $segmentKey,
                    $exception->getMessage()
                );

                if (!$skipErrors) {
                    throw new EventStoreOperationFailed($msg, Code::INTERNAL, $exception);
                }

                $promise->reject($msg);
            }
        ]);

        $pool->promise()->wait();
    }

    /**
     * Override to provide your own logic which determines which table name to use for a read operation.
     *
     * @param array $hints
     *
     * @return string
     */
    protected function determineTableNameForRead(array $hints)
    {
        return $this->determineTableNameForWrite($hints);
    }

    /**
     * Override to provide your own logic which determines which table name to use for a write operation.
     *
     * @param array $hints
     *
     * @return string
     */
    protected function determineTableNameForWrite(array $hints)
    {
        if (isset($hints['table_name'])) {
            return $hints['table_name'];
        }

        return $this->tableName;
    }

    /**
     * @param Event $event
     * @param array $item
     */
    protected function beforePutItem(Event $event, array &$item)
    {
        // allows for customization of DynamoDb item before it's pushed.
    }

    /**
     * @param array $item
     * @return Event
     */
    protected function unmarshalItem(array $item)
    {
        return $this->marshaler->unmarshal($item);
    }

    /**
     * When an expected etag is provided we can check the head of the stream to see if it's
     * at the expected state before appending events.
     *
     * @param string $streamId
     * @param array $hints
     * @param string $expectedEtag
     *
     * @throws OptimisticCheckFailed
     * @throws GdbotsPbjxException
     */
    private function optimisticCheck($streamId, array $hints, $expectedEtag)
    {
        $hints['consistent_read'] = true;
        $collection = $this->getEvents($streamId, null, 1, false, $hints);

        if (!$collection->count()) {
            throw new OptimisticCheckFailed(
                sprintf(
                    'The DynamoDb table [%s] has no events in stream [%s].',
                    $this->determineTableNameForRead($hints),
                    $streamId
                )
            );
        }

        $event = $collection->getIterator()->current();

        // todo: review this etag strategy (might need to make this more explicit/obvious)
        if ((string)$event->get('event_id') === $expectedEtag || md5($event->get('event_id')) === $expectedEtag) {
            return;
        }

        throw new OptimisticCheckFailed(
            sprintf(
                'The last event [%s] in DynamoDb table [%s] from stream [%s] doesn\'t match expected etag [%s].',
                $event->get('event_id'),
                $this->determineTableNameForRead($hints),
                $streamId,
                $expectedEtag
            )
        );
    }
}
