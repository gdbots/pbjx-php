<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventStore\DynamoDb;

use Aws\CommandPool;
use Aws\DynamoDb\DynamoDbClient;
use Aws\Exception\AwsException;
use Aws\ResultInterface;
use Gdbots\Pbj\Marshaler\DynamoDb\ItemMarshaler;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\ClassUtil;
use Gdbots\Pbj\Util\NumberUtil;
use Gdbots\Pbj\WellKnown\Identifier;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Pbjx\Event\EnrichContextEvent;
use Gdbots\Pbjx\EventStore\EventStore;
use Gdbots\Pbjx\EventStore\StreamSlice;
use Gdbots\Pbjx\Exception\EventNotFound;
use Gdbots\Pbjx\Exception\EventStoreOperationFailed;
use Gdbots\Pbjx\Exception\OptimisticCheckFailed;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\StreamId;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

class DynamoDbEventStore implements EventStore
{
    /**
     * The name of the DynamoDb table to write to.  This is the default value
     * and can change based on context provided.
     */
    protected string $tableName;
    protected Pbjx $pbjx;
    protected DynamoDbClient $client;
    protected EventDispatcher $dispatcher;
    protected LoggerInterface $logger;
    protected ItemMarshaler $marshaler;

    public function __construct(
        Pbjx $pbjx,
        DynamoDbClient $client,
        string $tableName,
        EventDispatcher $dispatcher,
        ?LoggerInterface $logger = null
    ) {
        $this->pbjx = $pbjx;
        $this->client = $client;
        $this->tableName = $tableName;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger ?: new NullLogger();
        $this->marshaler = new ItemMarshaler();
    }

    final public function createStorage(array $context = []): void
    {
        $context = $this->enrichContext(__FUNCTION__, $context);
        $table = new EventStoreTable();
        $table->create($this->client, $this->getTableNameForWrite($context));
    }

    final public function describeStorage(array $context = []): string
    {
        $context = $this->enrichContext(__FUNCTION__, $context);
        $table = new EventStoreTable();
        return $table->describe($this->client, $this->getTableNameForWrite($context));
    }

    final public function getEvent(Identifier $eventId, array $context = []): Message
    {
        $context = $this->enrichContext(__FUNCTION__, $context);
        $tableName = $this->getTableNameForRead($context);
        $key = $this->getItemKeyByEventId($eventId, $context);
        if (null === $key) {
            throw new EventNotFound();
        }

        try {
            $response = $this->client->getItem(['TableName' => $tableName, 'Key' => $key]);
        } catch (\Throwable $e) {
            if ($e instanceof AwsException) {
                $errorName = $e->getAwsErrorCode() ?: ClassUtil::getShortName($e);
                if ('ResourceNotFoundException' === $errorName) {
                    throw new EventNotFound();
                } elseif ('ProvisionedThroughputExceededException' === $errorName) {
                    $code = Code::RESOURCE_EXHAUSTED;
                } else {
                    $code = Code::UNAVAILABLE;
                }
            } else {
                $errorName = ClassUtil::getShortName($e);
                $code = Code::INTERNAL;
            }

            throw new EventStoreOperationFailed(
                sprintf(
                    '%s while getting [%s] from DynamoDb table [%s] using key [%s].',
                    $errorName,
                    $eventId,
                    $tableName,
                    json_encode($key)
                ),
                $code,
                $e
            );
        }

        if (!isset($response['Item']) || empty($response['Item'])) {
            throw new EventNotFound();
        }

        try {
            $this->marshaler->skipValidation(false);
            $event = $this->marshaler->unmarshal($response['Item']);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Item returned from DynamoDb table [{table_name}] for [{event_id}] could not be unmarshaled.',
                [
                    'exception'  => $e,
                    'item'       => $response['Item'],
                    'context'    => $context,
                    'table_name' => $tableName,
                    'event_id'   => (string)$eventId,
                ]
            );

            throw new EventNotFound();
        }

        return $event;
    }

    final public function getEvents(array $eventIds, array $context = []): array
    {
        $context = $this->enrichContext(__FUNCTION__, $context);

        // todo: optimize using batchGetItem
        $events = [];

        foreach ($eventIds as $eventId) {
            try {
                $event = $this->getEvent($eventId, $context);
                $events[(string)$event->get('event_id')] = $event;
            } catch (EventNotFound $nf) {
                // missing events are not exception worthy at this time
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        return $events;
    }

    final public function deleteEvent(Identifier $eventId, array $context = []): void
    {
        $context = $this->enrichContext(__FUNCTION__, $context);
        $tableName = $this->getTableNameForWrite($context);
        $key = $this->getItemKeyByEventId($eventId, $context);
        if (null === $key) {
            return;
        }

        try {
            $this->client->deleteItem(['TableName' => $tableName, 'Key' => $key]);
        } catch (\Throwable $e) {
            if ($e instanceof AwsException) {
                $errorName = $e->getAwsErrorCode() ?: ClassUtil::getShortName($e);
                if ('ResourceNotFoundException' === $errorName) {
                    // if it's already deleted, it's fine
                    return;
                } elseif ('ProvisionedThroughputExceededException' === $errorName) {
                    $code = Code::RESOURCE_EXHAUSTED;
                } else {
                    $code = Code::UNAVAILABLE;
                }
            } else {
                $errorName = ClassUtil::getShortName($e);
                $code = Code::INTERNAL;
            }

            throw new EventStoreOperationFailed(
                sprintf(
                    '%s while deleting [%s] from DynamoDb table [%s] using key [%s].',
                    $errorName,
                    $eventId,
                    $tableName,
                    json_encode($key)
                ),
                $code,
                $e
            );
        }
    }

    final public function getStreamSlice(StreamId $streamId, ?Microtime $since = null, int $count = 25, bool $forward = true, bool $consistent = false, array $context = []): StreamSlice
    {
        $context['stream_id'] = $streamId;
        $context = $this->enrichContext(__FUNCTION__, $context);
        $tableName = $this->getTableNameForRead($context);
        $reindexing = filter_var($context['reindexing'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $skipValidation = filter_var($context['skip_validation'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $count = NumberUtil::bound($count, 1, 100);

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
                ':v_id'   => ['S' => $this->streamIdToHashKey($streamId)],
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
            unset($params['Limit']);
        }

        try {
            $response = $this->client->query($params);
        } catch (\Throwable $e) {
            if ($e instanceof AwsException) {
                $errorName = $e->getAwsErrorCode() ?: ClassUtil::getShortName($e);
                if ('ProvisionedThroughputExceededException' === $errorName) {
                    $code = Code::RESOURCE_EXHAUSTED;
                } else {
                    $code = Code::UNAVAILABLE;
                }
            } else {
                $errorName = ClassUtil::getShortName($e);
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
        $this->marshaler->skipValidation($skipValidation);
        foreach ($response['Items'] as $item) {
            try {
                $events[] = $this->marshaler->unmarshal($item);
            } catch (\Throwable $e) {
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
        $this->marshaler->skipValidation(false);

        return new StreamSlice($events, $streamId, $forward, $consistent, $response['Count'] >= $count);
    }

    final public function putEvents(StreamId $streamId, array $events, ?string $expectedEtag = null, array $context = []): void
    {
        if (!count($events)) {
            // ignore empty events array
            return;
        }

        $context['stream_id'] = $streamId;
        $context = $this->enrichContext(__FUNCTION__, $context);

        if (null !== $expectedEtag) {
            $this->optimisticCheck($streamId, $expectedEtag, $context);
        }

        $this->marshaler->skipValidation(false);
        $tableName = $this->getTableNameForWrite($context);
        $hashKey = $this->streamIdToHashKey($streamId);

        $items = [];
        foreach ($events as $event) {
            $this->pbjx->triggerLifecycle($event);
            $item = $this->marshaler->marshal($event);
            $item[EventStoreTable::HASH_KEY_NAME] = ['S' => $hashKey];
            if ($event::schema()->hasMixin('gdbots:ncr:mixin:indexed')) {
                $item[EventStoreTable::INDEXED_KEY_NAME] = ['BOOL' => true];
            }
            $this->beforePutItem($item, $streamId, $event, $context);
            $items[] = [
                'Put' => [
                    'Item'      => $item,
                    'TableName' => $tableName,
                ],
            ];
        }

        try {
            $this->client->transactWriteItems([
                // todo: implement ClientRequestToken
                'TransactItems' => $items,
            ]);
        } catch (\Throwable $e) {
            if ($e instanceof AwsException) {
                $errorName = $e->getAwsErrorCode() ?: ClassUtil::getShortName($e);
                if ('ProvisionedThroughputExceededException' === $errorName) {
                    $code = Code::RESOURCE_EXHAUSTED;
                } else {
                    $code = Code::DATA_LOSS;
                }
            } else {
                $errorName = ClassUtil::getShortName($e);
                $code = Code::INTERNAL;
            }

            throw new EventStoreOperationFailed(
                sprintf(
                    '%s while putting events into DynamoDb table [%s] for stream [%s].',
                    $errorName,
                    $tableName,
                    $streamId
                ),
                $code,
                $e
            );
        }
    }

    final public function pipeEvents(StreamId $streamId, ?Microtime $since = null, ?Microtime $until = null, array $context = []): \Generator
    {
        $context['stream_id'] = $streamId;
        $context = $this->enrichContext(__FUNCTION__, $context);
        $consistent = filter_var($context['consistent'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $reindexing = filter_var($context['reindexing'] ?? false, FILTER_VALIDATE_BOOLEAN);

        do {
            $slice = $this->getStreamSlice($streamId, $since, 100, true, $consistent, $context);
            $since = $slice->getLastOccurredAt();

            /** @var Message $event */
            foreach ($slice as $event) {
                if (null !== $until && $event->get('occurred_at')->toFloat() >= $until->toFloat()) {
                    return;
                }

                if ($reindexing && !$event::schema()->hasMixin('gdbots:ncr:mixin:indexed')) {
                    continue;
                }

                yield $event;
            }
        } while ($slice->hasMore());
    }

    final public function pipeAllEvents(?Microtime $since = null, ?Microtime $until = null, array $context = []): \Generator
    {
        $context = $this->enrichContext(__FUNCTION__, $context);
        $generator = $this->doPipeAllEvents($since, $until, $context);

        /** @var \SplQueue $queue */
        $queue = $generator->current();

        do {
            $generator->next();
            while (!$queue->isEmpty()) {
                yield $queue->dequeue();
            }
        } while ($generator->valid());
    }

    protected function doPipeAllEvents(?Microtime $since = null, ?Microtime $until = null, array $context = []): \Generator
    {
        $tableName = $this->getTableNameForRead($context);
        $reindexing = filter_var($context['reindexing'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $skipErrors = filter_var($context['skip_errors'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $skipValidation = filter_var($context['skip_validation'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $totalSegments = NumberUtil::bound($context['total_segments'] ?? 16, 1, 64);
        $concurrency = NumberUtil::bound($context['concurrency'] ?? 25, 1, 100);

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
        $params['TotalSegments'] = $totalSegments;

        $pending = [];
        $iter2seg = ['prev' => [], 'next' => []];
        for ($segment = 0; $segment < $totalSegments; $segment++) {
            $params['Segment'] = $segment;
            $iter2seg['prev'][] = $segment;
            $pending[] = $this->client->getCommand('Scan', $params);
        }

        $this->marshaler->skipValidation($skipValidation);
        $queue = new \SplQueue();
        yield $queue;

        $fulfilled = function (ResultInterface $result, string $iterKey) use (
            $queue, $tableName, $context, $params, &$pending, &$iter2seg
        ) {
            $segment = $iter2seg['prev'][$iterKey];
            foreach ($result['Items'] as $item) {
                $streamId = null;

                try {
                    $streamId = $this->hashKeyToStreamId($item[EventStoreTable::HASH_KEY_NAME]['S']);
                    $event = $this->marshaler->unmarshal($item);
                } catch (\Throwable $e) {
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

                $queue->enqueue([$event, $streamId]);
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

            $errorName = $exception->getAwsErrorCode() ?: ClassUtil::getShortName($exception);
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

            $this->marshaler->skipValidation(false);
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
            $pool = new CommandPool($this->client, $commands, [
                'before'      => function () {
                    gc_collect_cycles();
                },
                'fulfilled'   => $fulfilled,
                'rejected'    => $rejected,
                'concurrency' => $concurrency,
            ]);
            $pool->promise()->wait();
            $iter2seg['prev'] = $iter2seg['next'];
            $iter2seg['next'] = [];
            yield;
        }

        yield;
        $this->marshaler->skipValidation(false);
    }

    protected function enrichContext(string $operation, array $context): array
    {
        if (isset($context['already_enriched'])) {
            return $context;
        }

        $event = new EnrichContextEvent('event_store', $operation, $context);
        $context = $this->dispatcher->dispatch($event, PbjxEvents::ENRICH_CONTEXT)->all();
        $context['already_enriched'] = true;
        return $context;
    }

    /**
     * Override to modify the stream id at write time.
     * Needed because the format of StreamId changed in
     * gdbots/schemas v2.x to include vendor: prefix.
     *
     * @param StreamId $streamId
     *
     * @return string
     */
    protected function streamIdToHashKey(StreamId $streamId): string
    {
        return $streamId->toString();
    }

    /**
     * Override to modify the stream id at read time.
     * Needed because the format of StreamId changed in
     * gdbots/schemas v2.x to include vendor: prefix.
     *
     * @param string $key
     *
     * @return StreamId
     */
    protected function hashKeyToStreamId(string $key): StreamId
    {
        return StreamId::fromString($key);
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
     * Add derived/virtual fields to the item before pushing to DynamoDb.
     * Typically used to create indices or set ttl field, etc.
     *
     * @param array    $item
     * @param StreamId $streamId
     * @param Message  $event
     * @param array    $context
     */
    protected function beforePutItem(array &$item, StreamId $streamId, Message $event, array $context): void
    {
        // override to customize
    }

    /**
     * When needing to get or delete an event by its id we must first
     * get the DynamoDb item key from the GSI.  At this time DynamoDb
     * does not support a batch get from a GSI so this can only handle
     * one event id at a time.
     *
     * @param Identifier $eventId
     * @param array      $context
     *
     * @return array|null
     */
    protected function getItemKeyByEventId(Identifier $eventId, array $context = []): ?array
    {
        $tableName = $this->getTableNameForRead($context);
        $params = [
            'TableName'                 => $tableName,
            'IndexName'                 => EventStoreTable::GSI_EVENT_ID_NAME,
            'Limit'                     => 1,
            'ExpressionAttributeNames'  => [
                '#hash' => EventStoreTable::GSI_EVENT_ID_HASH_KEY_NAME,
            ],
            'ExpressionAttributeValues' => [
                ':v_hash' => ['S' => (string)$eventId],
            ],
            'KeyConditionExpression'    => '#hash = :v_hash',
        ];

        try {
            $response = $this->client->query($params);
        } catch (\Throwable $e) {
            if ($e instanceof AwsException) {
                $errorName = $e->getAwsErrorCode() ?: ClassUtil::getShortName($e);
                if ('ProvisionedThroughputExceededException' === $errorName) {
                    $code = Code::RESOURCE_EXHAUSTED;
                } else {
                    $code = Code::UNAVAILABLE;
                }
            } else {
                $errorName = ClassUtil::getShortName($e);
                $code = Code::INTERNAL;
            }

            throw new EventStoreOperationFailed(
                sprintf(
                    '%s on IndexQuery [%s] on DynamoDb table [%s].',
                    $errorName,
                    EventStoreTable::GSI_EVENT_ID_NAME,
                    $tableName
                ),
                $code,
                $e
            );
        }

        if (!isset($response['Items']) || empty($response['Items'])) {
            return null;
        }

        $item = array_pop($response['Items']);
        return [
            EventStoreTable::HASH_KEY_NAME  => ['S' => $item[EventStoreTable::HASH_KEY_NAME]['S']],
            EventStoreTable::RANGE_KEY_NAME => ['N' => (string)$item[EventStoreTable::RANGE_KEY_NAME]['N']],
        ];
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
     */
    protected function optimisticCheck(StreamId $streamId, string $expectedEtag, array $context): void
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
        $eventId = (string)$event->get('event_id');
        if ($eventId === $expectedEtag || md5($eventId) === $expectedEtag) {
            return;
        }

        throw new OptimisticCheckFailed(
            sprintf(
                'The last event [%s:%s] in DynamoDb table [%s] from stream [%s] doesn\'t match expected etag [%s].',
                $eventId,
                md5($eventId),
                $this->getTableNameForRead($context),
                $streamId,
                $expectedEtag
            )
        );
    }
}
