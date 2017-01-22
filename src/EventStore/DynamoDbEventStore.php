<?php

namespace Gdbots\Pbjx\EventStore;

use Aws\CommandPool;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\WriteRequestBatch;
use Aws\Exception\AwsException;
use Aws\Result;
use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\Marshaler\DynamoDb\ItemMarshaler;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Pbjx\Exception\EventStoreOperationFailed;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\OptimisticCheckFailed;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Indexed\Indexed;
use Gdbots\Schemas\Pbjx\StreamId;
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
     * and can change based on context provided.
     *
     * @var string
     */
    protected $tableName;

    /** @var LoggerInterface */
    protected $logger;

    /** @var ItemMarshaler */
    protected $marshaler;

    /**
     * @param Pbjx                 $pbjx
     * @param DynamoDbClient       $client
     * @param string               $tableName
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
    final public function putEvents(StreamId $streamId, array $events, $expectedEtag = null, array $context = [])
    {
        if (!count($events)) {
            // ignore empty events array
            return;
        }

        $context['stream_id'] = $streamId->toString();

        if (null !== $expectedEtag) {
            $this->optimisticCheck($streamId, $expectedEtag, $context);
        }

        $tableName = $this->getTableNameForWrite($context);
        $batch = new WriteRequestBatch($this->client, [
            'table'     => $tableName,
            'autoflush' => false,
            'error'     => function (AwsException $e) use ($streamId, $tableName) {
                throw new EventStoreOperationFailed(
                    sprintf(
                        'Failed to put some or all events into DynamoDb table [%s] for stream [%s].',
                        $tableName,
                        $streamId
                    ),
                    Code::DATA_LOSS,
                    $e
                );
            },
        ]);

        /** @var Event[] $events */
        foreach ($events as $event) {
            if (!$event->isFrozen()) {
                $this->pbjx->triggerLifecycle($event);
            }

            $item = $this->marshaler->marshal($event);
            $item[DynamoDbEventStoreTable::HASH_KEY_NAME] = ['S' => (string) $streamId];
            if ($event instanceof Indexed) {
                $item[DynamoDbEventStoreTable::INDEXED_KEY_NAME] = ['BOOL' => true];
            }

            $this->beforePutItem($item, $event);
            $batch->put($item);
        }

        $batch->flush();
    }

    /**
     * {@inheritdoc}
     */
    final public function getEvents(
        StreamId $streamId,
        Microtime $since = null,
        $count = 25,
        $forward = true,
        $consistent = false,
        array $context = []
    ) {
        $context['stream_id'] = $streamId->toString();
        $tableName = $this->getTableNameForRead($context);
        $count = NumberUtils::bound($count, 1, 100);

        if ($forward) {
            $since = null !== $since ? $since->toString() : '0';
        } else {
            $since = null !== $since ? $since->toString() : Microtime::create()->toString();
        }

        $params = [
            'TableName'                 => $tableName,
            'ExpressionAttributeNames'  => [
                '#hash'  => DynamoDbEventStoreTable::HASH_KEY_NAME,
                '#range' => DynamoDbEventStoreTable::RANGE_KEY_NAME,
            ],
            'KeyConditionExpression'    => sprintf('#hash = :v_id AND #range %s :v_date', $forward ? '>' : '<'),
            'ExpressionAttributeValues' => [
                ':v_id'   => ['S' => (string) $streamId],
                ':v_date' => ['N' => $since],
            ],
            'ScanIndexForward'          => $forward,
            'Limit'                     => $count,
            'ConsistentRead'            => $consistent,
        ];
        $filterExpressions = [];

        if (isset($context['curie'])) {
            $params['ExpressionAttributeNames']['#schema'] = '_schema';
            $params['ExpressionAttributeValues'][':v_curie'] = ['S' => trim($context['curie'], '*')];
            $filterExpressions[] = 'contains(#schema, :v_curie)';
        }

        foreach (['s16', 's32', 's64', 's128', 's256'] as $shard) {
            if (isset($context[$shard])) {
                $params['ExpressionAttributeNames']["#{$shard}"] = $shard;
                $params['ExpressionAttributeValues'][":v_{$shard}"] = ['N' => (string) ((int) $context[$shard])];
                $filterExpressions[] = "#{$shard} = :v_{$shard}";
            }
        }

        if (!empty($filterExpressions)) {
            $params['FilterExpression'] = implode(' AND ', $filterExpressions);
        }

        try {
            $response = $this->client->query($params);
        } catch (AwsException $e) {
            throw new EventStoreOperationFailed(
                sprintf(
                    '%s while querying events from DynamoDb table [%s] for stream [%s].',
                    $e->getAwsErrorCode(),
                    $tableName,
                    $streamId
                ),
                'ProvisionedThroughputExceededException' === $e->getAwsErrorCode() ? Code::RESOURCE_EXHAUSTED : Code::UNAVAILABLE,
                $e
            );

        } catch (\Exception $e) {
            throw new EventStoreOperationFailed(
                sprintf('Failed to query events from DynamoDb table [%s] for stream [%s].', $tableName, $streamId),
                Code::INTERNAL,
                $e
            );
        }

        if (!$response['Count']) {
            return new StreamSlice([], $streamId, $forward, $consistent);
        }

        $events = [];
        foreach ($response['Items'] as $item) {
            try {
                $events[] = $this->marshaler->unmarshal($item);
            } catch (\Exception $e) {
                $this->logger->error(
                    'Item returned from DynamoDb table [{table_name}] from stream [{stream_id}] could not be unmarshaled.',
                    [
                        'exception'  => $e,
                        'item'       => $item,
                        'context'      => $context,
                        'table_name' => $tableName,
                        'stream_id'  => (string) $streamId,
                    ]
                );
            }
        }

        return new StreamSlice($events, $streamId, $forward, $consistent, $response['Count'] >= $count);
    }

    /**
     * {@inheritdoc}
     */
    final public function streamEvents(StreamId $streamId, Microtime $since = null, array $context = [])
    {
        $context['stream_id'] = $streamId->toString();

        do {
            $collection = $this->getEvents($streamId, $since, 100, true, false, $context);
            $since = $collection->getLastOccurredAt();

            foreach ($collection as $event) {
                yield $event;
            }

        } while ($collection->hasMore());
    }

    /**
     * {@inheritdoc}
     */
    final public function streamAllEvents(
        \Closure $callback,
        Microtime $since = null,
        Microtime $until = null,
        array $context = []
    ) {
        $tableName = $this->getTableNameForRead($context);
        $skipErrors = isset($context['skip_errors']) ? filter_var($context['skip_errors'], FILTER_VALIDATE_BOOLEAN) : false;
        $reindexing = isset($context['reindexing']) ? filter_var($context['reindexing'], FILTER_VALIDATE_BOOLEAN) : false;
        $limit = NumberUtils::bound(isset($context['limit']) ? $context['limit'] : 100, 1, 500);
        $totalSegments = NumberUtils::bound(isset($context['total_segments']) ? $context['total_segments'] : 16, 1, 64);
        $poolDelay = NumberUtils::bound(isset($context['pool_delay']) ? $context['pool_delay'] : 500, 100, 10000);

        $params = ['ExpressionAttributeNames' => [], 'ExpressionAttributeValues' => []];
        $filterExpressions = [];

        if (null !== $since) {
            $params['ExpressionAttributeNames']['#range'] = DynamoDbEventStoreTable::RANGE_KEY_NAME;
            $params['ExpressionAttributeValues'][':v_date_since'] = ['N' => $since->toString()];
            $filterExpressions[] = '#range > :v_date_since';
        }

        if (null !== $until) {
            $params['ExpressionAttributeNames']['#range'] = DynamoDbEventStoreTable::RANGE_KEY_NAME;
            $params['ExpressionAttributeValues'][':v_date_until'] = ['N' => $until->toString()];
            $filterExpressions[] = '#range < :v_date_until';
        }

        if ($reindexing) {
            $params['ExpressionAttributeNames']['#indexed'] = DynamoDbEventStoreTable::INDEXED_KEY_NAME;
            $filterExpressions[] = 'attribute_exists(#indexed)';
        }

        if (isset($context['curie'])) {
            $params['ExpressionAttributeNames']['#schema'] = '_schema';
            $params['ExpressionAttributeValues'][':v_curie'] = ['S' => trim($context['curie'], '*')];
            $filterExpressions[] = 'contains(#schema, :v_curie)';
        }

        foreach (['s16', 's32', 's64', 's128', 's256'] as $shard) {
            if (isset($context[$shard])) {
                $params['ExpressionAttributeNames']["#{$shard}"] = $shard;
                $params['ExpressionAttributeValues'][":v_{$shard}"] = ['N' => (string) ((int) $context[$shard])];
                $filterExpressions[] = "#{$shard} = :v_{$shard}";
            }
        }

        if (empty($params['ExpressionAttributeNames'])) {
            unset($params['ExpressionAttributeNames']);
        }

        if (empty($params['ExpressionAttributeValues'])) {
            unset($params['ExpressionAttributeValues']);
        }

        if (!empty($filterExpressions)) {
            $params['FilterExpression'] = implode(' AND ', $filterExpressions);
        }

        $this->beforeStreamAllEvents($params, $context, $since, $until);

        $params['TableName'] = $tableName;
        $params['Limit'] = $limit;
        $params['TotalSegments'] = $totalSegments;

        $pending = [];
        $iter2seg = ['prev' => [], 'next' => []];
        for ($segment = 0; $segment < $totalSegments; $segment++) {
            $params['Segment'] = $segment;
            $iter2seg['prev'][] = $segment;
            $pending[] = $this->client->getCommand('Scan', $params);
        }

        $fulfilled = function (Result $result, $iterKey)
        use ($callback, $tableName, $context, $params, &$pending, &$iter2seg) {
            $segment = $iter2seg['prev'][$iterKey];

            foreach ($result['Items'] as $item) {
                $streamId = null;

                try {
                    $streamId = StreamId::fromString($item[DynamoDbEventStoreTable::HASH_KEY_NAME]['S']);
                    $event = $this->marshaler->unmarshal($item);
                } catch (\Exception $e) {
                    $this->logger->error(
                        'Item returned from DynamoDb table [{table_name}] segment [{segment}] ' .
                        'from stream [{stream_id}] could not be unmarshaled.',
                        [
                            'exception'  => $e,
                            'item'       => $item,
                            'context'      => $context,
                            'table_name' => $tableName,
                            'segment'    => $segment,
                            'stream_id'  => (string) $streamId,
                        ]
                    );

                    continue;
                }

                $callback($event, $streamId);
            }

            if ($result['LastEvaluatedKey']) {
                $params['Segment'] = $segment;
                $params['ExclusiveStartKey'] = $result['LastEvaluatedKey'];
                $pending[] = $this->client->getCommand('Scan', $params);
                $iter2seg['next'][] = $segment;
            } else {
                $this->logger->info(
                    'Scan of DynamoDb table [{table_name}] segment [{segment}] is complete.',
                    [
                        'context'      => $context,
                        'table_name' => $tableName,
                        'segment'    => $segment,
                    ]
                );
            }
        };

        $rejected = function (AwsException $exception, $iterKey, PromiseInterface $aggregatePromise)
        use ($tableName, $context, $skipErrors, &$iter2seg) {
            $segment = $iter2seg['prev'][$iterKey];

            if ($skipErrors) {
                $this->logger->error(
                    'Scan failed on DynamoDb table [{table_name}] segment [{segment}].',
                    [
                        'exception'  => $exception,
                        'context'      => $context,
                        'table_name' => $tableName,
                        'segment'    => $segment,
                    ]
                );

                return;
            }

            $aggregatePromise->reject(
                new EventStoreOperationFailed(
                    sprintf('Scan failed on DynamoDb table [%s] segment [%s].', $tableName, $segment),
                    Code::INTERNAL,
                    $exception
                )
            );
        };

        while (count($pending) > 0) {
            $commands = $pending;
            $pending = [];
            $pool = new CommandPool($this->client, $commands, ['fulfilled' => $fulfilled, 'rejected' => $rejected]);
            $pool->promise()->wait();
            $iter2seg['prev'] = $iter2seg['next'];
            $iter2seg['next'] = [];

            if (count($pending) > 0) {
                $this->logger->info(sprintf('Pausing for %d milliseconds.', $poolDelay));
                usleep($poolDelay * 1000);
            }
        }
    }

    /**
     * Override to provide your own logic which determines which table name to use for a read operation.
     *
     * @param array $context
     *
     * @return string
     */
    protected function getTableNameForRead(array $context)
    {
        return $this->getTableNameForWrite($context);
    }

    /**
     * Override to provide your own logic which determines which table name to use for a write operation.
     *
     * @param array $context
     *
     * @return string
     */
    protected function getTableNameForWrite(array $context)
    {
        if (isset($context['table_name'])) {
            return $context['table_name'];
        }

        return $this->tableName;
    }

    /**
     * @param array $item
     * @param Event $event
     */
    protected function beforePutItem(array &$item, Event $event)
    {
        // allows for customization of DynamoDb item before it's pushed.
    }

    /**
     * @param array     $params
     * @param array     $context
     * @param Microtime $since
     * @param Microtime $until
     */
    protected function beforeStreamAllEvents(
        array &$params,
        array $context,
        Microtime $since = null,
        Microtime $until = null
    ) {
        // allows for customization of the DynamoDb parallel scan parameters before the scan runs.
    }

    /**
     * When an expected etag is provided we can check the head of the stream to see if it's
     * at the expected state before appending events.
     *
     * @param StreamId $streamId
     * @param string   $expectedEtag
     * @param array    $context
     *
     * @throws OptimisticCheckFailed
     * @throws GdbotsPbjxException
     */
    private function optimisticCheck(StreamId $streamId, $expectedEtag, array $context)
    {
        $collection = $this->getEvents($streamId, null, 1, false, true, $context);

        if (!$collection->count()) {
            throw new OptimisticCheckFailed(
                sprintf(
                    'The DynamoDb table [%s] has no events in stream [%s].',
                    $this->getTableNameForRead($context),
                    $streamId
                )
            );
        }

        $event = $collection->getIterator()->current();

        // todo: review this etag strategy (might need to make this more explicit/obvious)
        if ((string) $event->get('event_id') === $expectedEtag || md5($event->get('event_id')) === $expectedEtag) {
            return;
        }

        throw new OptimisticCheckFailed(
            sprintf(
                'The last event [%s] in DynamoDb table [%s] from stream [%s] doesn\'t match expected etag [%s].',
                $event->get('event_id'),
                $this->getTableNameForRead($context),
                $streamId,
                $expectedEtag
            )
        );
    }
}
