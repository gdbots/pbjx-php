<?php

namespace Gdbots\Pbjx\EventStore;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;

final class DynamoDbEventStoreTable
{
    const SCHEMA_VERSION = 'v1';
    const HASH_KEY_NAME = '__stream_id';
    const RANGE_KEY_NAME = 'occurred_at';
    const INDEXED_KEY_NAME = '__indexed';

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
    public function __construct(DynamoDbClient $client, $name)
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
                ['AttributeName' => self::HASH_KEY_NAME, 'AttributeType' => 'S'],
                ['AttributeName' => self::RANGE_KEY_NAME, 'AttributeType' => 'N'],
                ['AttributeName' => 'event_id', 'AttributeType' => 'S'],
                ['AttributeName' => self::INDEXED_KEY_NAME, 'AttributeType' => 'BOOL'],
            ],
            'KeySchema' => [
                ['AttributeName' => self::HASH_KEY_NAME, 'KeyType' => 'HASH'],
                ['AttributeName' => self::RANGE_KEY_NAME, 'KeyType' => 'RANGE'],
            ],
            'GlobalSecondaryIndexes' => [
                [
                    'IndexName' => 'event_id_index',
                    'KeySchema' => [
                        ['AttributeName' => 'event_id', 'KeyType' => 'HASH'],
                    ],
                    'Projection' => [
                        'ProjectionType' => 'KEYS_ONLY',
                    ],
                    'ProvisionedThroughput' => [
                        'ReadCapacityUnits'  => 2,
                        'WriteCapacityUnits' => 2
                    ]
                ],
            ],
            'StreamSpecification' => [
                'StreamEnabled' => true,
                'StreamViewType' => 'NEW_IMAGE',
            ],
            'ProvisionedThroughput' => [
                'ReadCapacityUnits'  => 2,
                'WriteCapacityUnits' => 2
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
