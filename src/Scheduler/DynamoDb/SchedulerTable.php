<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Scheduler\DynamoDb;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbjx\Exception\SchedulerOperationFailed;
use Gdbots\Schemas\Pbjx\Enum\Code;

final class SchedulerTable
{
    const SCHEMA_VERSION = 'v1';
    const HASH_KEY_NAME = 'job_id';
    const SEND_AT_KEY_NAME = 'send_at';
    const TTL_KEY_NAME = 'ttl';
    const EXECUTION_ARN_KEY_NAME = 'execution_arn';
    const PAYLOAD_KEY_NAME = 'payload';

    /**
     * Creates a DynamoDb table with the scheduler schema.
     *
     * @param DynamoDbClient $client
     * @param string         $tableName
     *
     * @throws SchedulerOperationFailed
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
                'TableName'               => $tableName,
                'AttributeDefinitions'    => [
                    ['AttributeName' => self::HASH_KEY_NAME, 'AttributeType' => 'S'],
                ],
                'KeySchema'               => [
                    ['AttributeName' => self::HASH_KEY_NAME, 'KeyType' => 'HASH'],
                ],
                'TimeToLiveSpecification' => [
                    'Enabled'       => true,
                    'AttributeName' => self::TTL_KEY_NAME,
                ],
                'ProvisionedThroughput'   => [
                    'ReadCapacityUnits'  => 2,
                    'WriteCapacityUnits' => 2,
                ],
            ]);

            $client->waitUntil('TableExists', ['TableName' => $tableName]);
        } catch (\Exception $e) {
            throw new SchedulerOperationFailed(
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
     * @throws SchedulerOperationFailed
     */
    public function describe(DynamoDbClient $client, string $tableName): string
    {
        try {
            $result = $client->describeTable(['TableName' => $tableName]);
            return json_encode($result->toArray(), JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            throw new SchedulerOperationFailed(
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
