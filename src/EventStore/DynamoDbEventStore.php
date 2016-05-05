<?php

namespace Gdbots\Pbjx\EventStore;

use Aws\DynamoDb\DynamoDbClient;
use Gdbots\Common\Microtime;
use Gdbots\Pbj\Marshaler\DynamoDb\ItemMarshaler;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DynamoDbEventStore implements EventStore
{
    /** @var DynamoDbClient */
    protected $client;

    /** @var LoggerInterface */
    protected $logger;

    /** @var ItemMarshaler */
    protected $marshaler;

    /**
     * @param DynamoDbClient $client
     * @param LoggerInterface|null $logger
     */
    public function __construct(DynamoDbClient $client, LoggerInterface $logger = null)
    {
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
            $item = $this->marshaler->marshal($event);
            $item['__stream_id'] = ['S' => $streamId];
            $this->beforePutItem($event, $item);
            $puts[] = ['PutRequest' => ['Item' => $item]];
        }

        $response = $this->client->batchWriteItem([
            'RequestItems' => [$this->getTableName() => [$puts]]
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getEvents($streamId, Microtime $start = null, $count = 25, $forward = true)
    {
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
