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
    const GSI_EVENT_ID_NAME = 'event_id_index';
    const GSI_EVENT_ID_HASH_KEY_NAME = 'event_id';

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
                ['AttributeName' => self::GSI_EVENT_ID_HASH_KEY_NAME, 'AttributeType' => 'S'],
            ],
            'KeySchema' => [
                ['AttributeName' => self::HASH_KEY_NAME, 'KeyType' => 'HASH'],
                ['AttributeName' => self::RANGE_KEY_NAME, 'KeyType' => 'RANGE'],
            ],
            'GlobalSecondaryIndexes' => [
                [
                    'IndexName' => self::GSI_EVENT_ID_NAME,
                    'KeySchema' => [
                        ['AttributeName' => self::GSI_EVENT_ID_HASH_KEY_NAME, 'KeyType' => 'HASH'],
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
