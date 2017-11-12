<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventStore\DynamoDb;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbjx\Exception\EventStoreOperationFailed;
use Gdbots\Schemas\Pbjx\Enum\Code;

final class EventStoreTable
{
    const SCHEMA_VERSION = 'v1';
    const HASH_KEY_NAME = '__stream_id';
    const RANGE_KEY_NAME = 'occurred_at';
    const INDEXED_KEY_NAME = '__indexed';
    const GSI_EVENT_ID_NAME = 'event_id_index';
    const GSI_EVENT_ID_HASH_KEY_NAME = 'event_id';

    /**
     * Creates a DynamoDb table with the event store schema.
     *
     * @param DynamoDbClient $client
     * @param string         $tableName
     *
     * @throws EventStoreOperationFailed
     */
    public function create(DynamoDbClient $client, string $tableName): void
    {
        try {
            $client->describeTable(['TableName' => $tableName]);
            return;
        } catch (DynamoDbException $e) {
            // table doesn't exist, create it below
        }

        try {
            $client->createTable([
                'TableName'              => $tableName,
                'AttributeDefinitions'   => [
                    ['AttributeName' => self::HASH_KEY_NAME, 'AttributeType' => 'S'],
                    ['AttributeName' => self::RANGE_KEY_NAME, 'AttributeType' => 'N'],
                    ['AttributeName' => self::GSI_EVENT_ID_HASH_KEY_NAME, 'AttributeType' => 'S'],
                ],
                'KeySchema'              => [
                    ['AttributeName' => self::HASH_KEY_NAME, 'KeyType' => 'HASH'],
                    ['AttributeName' => self::RANGE_KEY_NAME, 'KeyType' => 'RANGE'],
                ],
                'GlobalSecondaryIndexes' => [
                    [
                        'IndexName'             => self::GSI_EVENT_ID_NAME,
                        'KeySchema'             => [
                            ['AttributeName' => self::GSI_EVENT_ID_HASH_KEY_NAME, 'KeyType' => 'HASH'],
                        ],
                        'Projection'            => [
                            'ProjectionType' => 'KEYS_ONLY',
                        ],
                        'ProvisionedThroughput' => [
                            'ReadCapacityUnits'  => 2,
                            'WriteCapacityUnits' => 2,
                        ],
                    ],
                ],
                'StreamSpecification'    => [
                    'StreamEnabled'  => true,
                    'StreamViewType' => 'NEW_IMAGE',
                ],
                'ProvisionedThroughput'  => [
                    'ReadCapacityUnits'  => 2,
                    'WriteCapacityUnits' => 2,
                ],
            ]);

            $client->waitUntil('TableExists', ['TableName' => $tableName]);
        } catch (\Exception $e) {
            throw new EventStoreOperationFailed(
                sprintf(
                    '%s::Unable to create table [%s] in region [%s].',
                    ClassUtils::getShortName($this),
                    $tableName,
                    $client->getRegion()
                ),
                Code::INTERNAL,
                $e
            );
        }
    }

    /**
     * Describes a DynamoDb table.
     *
     * @param DynamoDbClient $client
     * @param string         $tableName
     *
     * @return string
     *
     * @throws EventStoreOperationFailed
     */
    public function describe(DynamoDbClient $client, string $tableName): string
    {
        try {
            $result = $client->describeTable(['TableName' => $tableName]);
            return json_encode($result->toArray(), JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            throw new EventStoreOperationFailed(
                sprintf(
                    '%s::Unable to describe table [%s] in region [%s].',
                    ClassUtils::getShortName($this),
                    $tableName,
                    $client->getRegion()
                ),
                Code::INTERNAL,
                $e
            );
        }
    }
}
