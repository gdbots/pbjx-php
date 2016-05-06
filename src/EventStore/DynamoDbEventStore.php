<?php

namespace Gdbots\Pbjx\EventStore;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Exception\AwsException;
use Gdbots\Common\Microtime;
use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\Marshaler\DynamoDb\ItemMarshaler;
use Gdbots\Pbjx\Pbjx;
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
        $puts = [];

        /*
         * todo: handle in loop
         * chunk if > 25 (or throw exception?)
         * handle unprocessed items
         *
         * http://docs.aws.amazon.com/amazondynamodb/latest/developerguide/LowLevelPHPItemCRUD.html#WriteMultipleItemsLowLevelPHP
         *
         * throw exceptions on marshalling
         * add derived fields (mixins?)
         * log critical issues or just throw exception?
         *
         */
        foreach ($events as $event) {
            $this->pbjx->triggerLifecycle($event);

            $item = $this->marshaler->marshal($event);
            $item[DynamoDbEventStoreTable::HASH_KEY_NAME] = ['S' => $streamId];
            $this->beforePutItem($event, $item);
            $puts[] = ['PutRequest' => ['Item' => $item]];
        }

        $response = $this->client->batchWriteItem([
            'RequestItems' => [$this->getTableName() => $puts]
        ]);
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
            echo $e;
        }


        if (!$response->count()) {
            return new EventCollection([], $streamId, $forward);
        }

        $events = [];
        foreach ($response['Items'] as $result) {
            try {
                /** @var Event $event */
                $events[] = $this->marshaler->unmarshal($result);
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf(
                        'Event returned from Dynamo table [%s:%s] could not be unmarshaled.  %s',
                        $tableName,
                        ClassUtils::getShortName($e) . '::' . $e->getMessage()
                    )
                );
            }
        }

        return new EventCollection($events, $streamId, $forward, $response->count() >= $count);
    }

    /**
     * {@inheritdoc}
     */
    public function streamEvents($streamId, Microtime $start = null)
    {
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
     * @param Event $event
     * @param array $item
     */
    protected function beforePutItem(Event $event, array &$item)
    {
        // allow for customization of DynamoDb before it's pushed.
    }
}
