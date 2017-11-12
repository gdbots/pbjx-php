<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventStore\DynamoDb;

use Aws\CommandPool;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\WriteRequestBatch;
use Aws\Exception\AwsException;
use Aws\ResultInterface;
use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\Marshaler\DynamoDb\ItemMarshaler;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Pbjx\EventStore\EventStore;
use Gdbots\Pbjx\EventStore\StreamSlice;
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
    /**
     * The name of the DynamoDb table to write to.  This is the default value
     * and can change based on context provided.
     *
     * @var string
     */
    protected $tableName;

    /** @var Pbjx */
    private $pbjx;

    /** @var DynamoDbClient */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    /** @var ItemMarshaler */
    private $marshaler;

    /**
     * @param Pbjx            $pbjx
     * @param DynamoDbClient  $client
     * @param string          $tableName
     * @param LoggerInterface $logger
     */
    public function __construct(Pbjx $pbjx, DynamoDbClient $client, string $tableName, ?LoggerInterface $logger = null)
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
    final public function createStorage(array $context = []): void
    {
        $table = new EventStoreTable();
        $table->create($this->client, $this->getTableNameForWrite($context));
    }

    /**
     * {@inheritdoc}
     */
    final public function describeStorage(array $context = []): string
    {
        $table = new EventStoreTable();
        return $table->describe($this->client, $this->getTableNameForWrite($context));
    }

    /**
     * {@inheritdoc}
     */
    final public function getStreamSlice(StreamId $streamId, ?Microtime $since = null, int $count = 25, bool $forward = true, bool $consistent = false, array $context = []): StreamSlice
    {
        $context['stream_id'] = $streamId->toString();
        $tableName = $this->getTableNameForRead($context);
        $reindexing = filter_var($context['reindexing'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $count = NumberUtils::bound($count, 1, 100);

        if ($forward) {
            $since = null !== $since ? $since->toString() : '0';
        } else {
            $since = null !== $since ? $since->toString() : Microtime::create()->toString();
        }

        $params = [
            'TableName'                 => $tableName,
            'ExpressionAttributeNames'  => [
                '#hash'  => EventStoreTable::HASH_KEY_NAME,
                '#range' => EventStoreTable::RANGE_KEY_NAME,
            ],
            'KeyConditionExpression'    => sprintf('#hash = :v_id AND #range %s :v_date', $forward ? '>' : '<'),
            'ExpressionAttributeValues' => [
                ':v_id'   => ['S' => (string)$streamId],
                ':v_date' => ['N' => $since],
            ],
            'ScanIndexForward'          => $forward,
            'Limit'                     => $count,
            'ConsistentRead'            => $consistent,
        ];
        $filterExpressions = [];

        if ($reindexing) {
            $params['ExpressionAttributeNames']['#indexed'] = EventStoreTable::INDEXED_KEY_NAME;
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
                $params['ExpressionAttributeValues'][":v_{$shard}"] = ['N' => (string)((int)$context[$shard])];
                $filterExpressions[] = "#{$shard} = :v_{$shard}";
            }
        }

        if (!empty($filterExpressions)) {
            $params['FilterExpression'] = implode(' AND ', $filterExpressions);
        }

        try {
            $response = $this->client->query($params);
        } catch (\Exception $e) {
            if ($e instanceof AwsException) {
                $errorName = $e->getAwsErrorCode() ?: ClassUtils::getShortName($e);
                if ('ProvisionedThroughputExceededException' === $errorName) {
                    $code = Code::RESOURCE_EXHAUSTED;
                } else {
                    $code = Code::UNAVAILABLE;
                }
            } else {
                $errorName = ClassUtils::getShortName($e);
                $code = Code::INTERNAL;
            }

            throw new EventStoreOperationFailed(
                sprintf(
                    '%s while getting StreamSlice from DynamoDb table [%s] for stream [%s].',
                    $errorName,
                    $tableName,
                    $streamId
                ),
                $code,
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
                        'context'    => $context,
                        'table_name' => $tableName,
                        'stream_id'  => (string)$streamId,
                    ]
                );
            }
        }

        return new StreamSlice($events, $streamId, $forward, $consistent, $response['Count'] >= $count);
    }

    /**
     * {@inheritdoc}
     */
    final public function putEvents(StreamId $streamId, array $events, ?string $expectedEtag = null, array $context = []): void
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
                        '%s while putting events into DynamoDb table [%s] for stream [%s].',
                        $e->getAwsErrorCode() ?: ClassUtils::getShortName($e),
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
            $this->pbjx->triggerLifecycle($event);
            $item = $this->marshaler->marshal($event);
            $item[EventStoreTable::HASH_KEY_NAME] = ['S' => (string)$streamId];
            if ($event instanceof Indexed) {
                $item[EventStoreTable::INDEXED_KEY_NAME] = ['BOOL' => true];
            }
            $batch->put($item);
        }

        $batch->flush();
    }

    /**
     * {@inheritdoc}
     */
    final public function pipeEvents(StreamId $streamId, callable $receiver, ?Microtime $since = null, ?Microtime $until = null, array $context = []): void
    {
        $reindexing = filter_var($context['reindexing'] ?? false, FILTER_VALIDATE_BOOLEAN);

        do {
            $slice = $this->getStreamSlice($streamId, $since, 100, true, false, $context);
            $since = $slice->getLastOccurredAt();

            foreach ($slice as $event) {
                if (null !== $until && $event->get('occurred_at')->toFloat() >= $until->toFloat()) {
                    return;
                }

                if ($reindexing && !$event instanceof Indexed) {
                    continue;
                }

                $receiver($event, $streamId);
            }
        } while ($slice->hasMore());
    }

    /**
     * {@inheritdoc}
     */
    final public function pipeAllEvents(callable $receiver, ?Microtime $since = null, ?Microtime $until = null, array $context = []): void
    {
        $tableName = $this->getTableNameForRead($context);
        $skipErrors = filter_var($context['skip_errors'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $reindexing = filter_var($context['reindexing'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $limit = NumberUtils::bound($context['limit'] ?? 100, 1, 500);
        $totalSegments = NumberUtils::bound($context['total_segments'] ?? 16, 1, 64);
        $poolDelay = NumberUtils::bound($context['pool_delay'] ?? 500, 100, 10000);

        $params = ['ExpressionAttributeNames' => [], 'ExpressionAttributeValues' => []];
        $filterExpressions = [];

        if (null !== $since) {
            $params['ExpressionAttributeNames']['#range'] = EventStoreTable::RANGE_KEY_NAME;
            $params['ExpressionAttributeValues'][':v_date_since'] = ['N' => $since->toString()];
            $filterExpressions[] = '#range > :v_date_since';
        }

        if (null !== $until) {
            $params['ExpressionAttributeNames']['#range'] = EventStoreTable::RANGE_KEY_NAME;
            $params['ExpressionAttributeValues'][':v_date_until'] = ['N' => $until->toString()];
            $filterExpressions[] = '#range < :v_date_until';
        }

        if ($reindexing) {
            $params['ExpressionAttributeNames']['#indexed'] = EventStoreTable::INDEXED_KEY_NAME;
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
                $params['ExpressionAttributeValues'][":v_{$shard}"] = ['N' => (string)((int)$context[$shard])];
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

        $fulfilled = function (ResultInterface $result, string $iterKey) use (
            $receiver, $tableName, $context, $params, &$pending, &$iter2seg
        ) {
            $segment = $iter2seg['prev'][$iterKey];

            foreach ($result['Items'] as $item) {
                $streamId = null;

                try {
                    $streamId = StreamId::fromString($item[EventStoreTable::HASH_KEY_NAME]['S']);
                    $event = $this->marshaler->unmarshal($item);
                } catch (\Exception $e) {
                    $this->logger->error(
                        'Item returned from DynamoDb table [{table_name}] segment [{segment}] ' .
                        'from stream [{stream_id}] could not be unmarshaled.',
                        [
                            'exception'  => $e,
                            'item'       => $item,
                            'context'    => $context,
                            'table_name' => $tableName,
                            'segment'    => $segment,
                            'stream_id'  => (string)$streamId,
                        ]
                    );

                    continue;
                }

                $receiver($event, $streamId);
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
                        'context'    => $context,
                        'table_name' => $tableName,
                        'segment'    => $segment,
                    ]
                );
            }
        };

        $rejected = function (AwsException $exception, string $iterKey, PromiseInterface $aggregatePromise) use (
            $tableName, $context, $skipErrors, &$iter2seg
        ) {
            $segment = $iter2seg['prev'][$iterKey];

            $errorName = $exception->getAwsErrorCode() ?: ClassUtils::getShortName($exception);
            if ('ProvisionedThroughputExceededException' === $errorName) {
                $code = Code::RESOURCE_EXHAUSTED;
            } else {
                $code = Code::UNAVAILABLE;
            }

            if ($skipErrors) {
                $this->logger->error(
                    sprintf(
                        '%s while scanning DynamoDb table [{table_name}] segment [{segment}].',
                        $errorName
                    ),
                    [
                        'exception'  => $exception,
                        'context'    => $context,
                        'table_name' => $tableName,
                        'segment'    => $segment,
                    ]
                );

                return;
            }

            $aggregatePromise->reject(
                new EventStoreOperationFailed(
                    sprintf(
                        '%s while scanning DynamoDb table [%s] segment [%s].',
                        $errorName,
                        $tableName,
                        $segment
                    ),
                    $code,
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
     * Override to provide your own logic which determines which
     * table name to use for a read operation.
     *
     * @param array $context
     *
     * @return string
     */
    protected function getTableNameForRead(array $context): string
    {
        return $this->getTableNameForWrite($context);
    }

    /**
     * Override to provide your own logic which determines which
     * table name to use for a write operation.
     *
     * @param array $context
     *
     * @return string
     */
    protected function getTableNameForWrite(array $context): string
    {
        return $context['table_name'] ?? $this->tableName;
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
    private function optimisticCheck(StreamId $streamId, string $expectedEtag, array $context): void
    {
        $slice = $this->getStreamSlice($streamId, null, 1, false, true, $context);

        if (!$slice->count()) {
            throw new OptimisticCheckFailed(
                sprintf(
                    'The DynamoDb table [%s] has no events in stream [%s].',
                    $this->getTableNameForRead($context),
                    $streamId
                )
            );
        }

        $event = $slice->getIterator()->current();

        // todo: review this etag strategy (need to make this more explicit/obvious)
        if ((string)$event->get('event_id') === $expectedEtag || md5($event->get('event_id')) === $expectedEtag) {
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
