<?php

namespace Gdbots\Pbjx\EventStore;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;

final class DynamoDbEventStoreTable
{
    const SCHEMA_VERSION = 'v1';
    const DEFAULT_NAME = 'event_store_v1';

    /** @var DynamoDbClient */
    private $client;

    /**
     * The name of the DynamoDb table.
     *
     * @var string
     */
    private $name;

    /**
     * @param DynamoDbClient $client
     * @param string $name
     */
    public function __construct(DynamoDbClient $client, $name = self::DEFAULT_NAME)
    {
        $this->client = $client;
        $this->name = $name;
    }

    /**
     * Creates a DynamoDb table with the event store schema.
     */
    public function create()
    {
        try {
            $this->client->describeTable(['TableName' => $this->name]);
            return;
        } catch (DynamoDbException $e)  {
            // table doesn't exist, create it below
        }

        $this->client->createTable([
            'TableName' => $this->name,
            'AttributeDefinitions' => [
                ['AttributeName' => '__stream_id', 'AttributeType' => 'S'],
                ['AttributeName' => 'occurred_at', 'AttributeType' => 'N'],
            ],
            'KeySchema' => [
                ['AttributeName' => '__stream_id', 'KeyType' => 'HASH'],
                ['AttributeName' => 'occurred_at', 'KeyType' => 'RANGE'],
            ],
            'ProvisionedThroughput' => [
                'ReadCapacityUnits'  => 5,
                'WriteCapacityUnits' => 5
            ]
        ]);

        $this->client->waitUntil('TableExists', ['TableName' => $this->name]);
    }

    /**
     * @return string
     */
    public function describe()
    {
        $result = $this->client->describeTable(['TableName' => $this->name]);
        return json_encode($result->toArray(), JSON_PRETTY_PRINT);
    }
}
