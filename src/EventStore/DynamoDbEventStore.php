<?php

namespace Gdbots\Pbjx\EventStore;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\WriteRequestBatch;
use Aws\Exception\AwsException;
use Gdbots\Common\Microtime;
use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\Marshaler\DynamoDb\ItemMarshaler;
use Gdbots\Pbjx\Exception\EventStoreOperationFailed;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DynamoDbEventStore implements EventStore
{
    /** @var Pbjx */
    protected $pbjx;

    /** @var DynamoDbClient */
    protected $client;

    /** @var LoggerInterface */
    protected $logger;

    /** @var ItemMarshaler */
    protected $marshaler;

    /**
     * @param Pbjx $pbjx
     * @param DynamoDbClient $client
     * @param LoggerInterface|null $logger
     */
    public function __construct(Pbjx $pbjx, DynamoDbClient $client, LoggerInterface $logger = null)
    {
        $this->pbjx = $pbjx;
        $this->client = $client;
        $this->logger = $logger ?: new NullLogger();
        $this->marshaler = new ItemMarshaler();
    }

    /**
     * {@inheritdoc}
     */
    public function putEvents($streamId, array $events)
    {
        $tableName = $this->getTableName();
        $batch = new WriteRequestBatch($this->client, [
            'table' => $tableName,
            'autoflush' => false,
            'error' => function(AwsException $e) use ($streamId, $tableName) {
                throw new EventStoreOperationFailed(
                    sprintf(
                        'Failed to put some or all events into DynamoDb table [%s:%s] with message: %s',
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
            $this->beforePutItem($streamId, $event, $item);
            $batch->put($item);
        }

        $batch->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getEvents($streamId, Microtime $start = null, $count = 25, $forward = true)
    {
        $tableName = $this->getTableName();
        $count = NumberUtils::bound($count, 1, 100);

        if ($forward) {
            $start = null !== $start ? $start->toString() : '0';
        } else {
            $start = null !== $start ? $start->toString() : Microtime::create()->toString();
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
                    ':v_date' => ['N' => $start]
                ],
                'ScanIndexForward' => $forward,
                'Limit' => $count,
                'ConsistentRead' => false
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
                    'Failed to query events from DynamoDb table [%s:%s] with message: %s',
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
                    'Failed to query events from DynamoDb table [%s:%s] with message: %s',
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
                /** @var Event $event */
                $events[] = $this->marshaler->unmarshal($item);
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf(
                        'Item returned from DynamoDb table [%s] could not be unmarshaled. %s',
                        $tableName,
                        ClassUtils::getShortName($e) . '::' . $e->getMessage()
                    ),
                    [
                        'exception' => $e,
                        'item' => $item
                    ]
                );
            }
        }

        return new EventCollection($events, $streamId, $forward, $response['Count'] >= $count);
    }

    /**
     * {@inheritdoc}
     */
    public function streamEvents($streamId, Microtime $start = null)
    {
        do {
            $collection = $this->getEvents($streamId, $start, 100);
            $start = $collection->getLastMicrotime();

            foreach ($collection as $item) {
                yield $item;
            }

        } while ($collection->hasMore());
    }

    /**
     * Override to provide your own table name.  Intentionally a method and not a
     * constructor argument or instance variable to allow for dynamic tables
     * for multi-tenant applications.
     *
     * @return string
     */
    protected function getTableName()
    {
        return DynamoDbEventStoreTable::DEFAULT_NAME;
    }

    /**
     * @param string $streamId
     * @param Event $event
     * @param array $item
     */
    protected function beforePutItem($streamId, Event $event, array &$item)
    {
        // allows for customization of DynamoDb item before it's pushed.
    }
}
