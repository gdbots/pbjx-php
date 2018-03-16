<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Scheduler\DynamoDb;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Exception\AwsException;
use Aws\Sfn\SfnClient;
use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\DateUtils;
use Gdbots\Pbj\Marshaler\DynamoDb\ItemMarshaler;
use Gdbots\Pbj\WellKnown\TimeUuidIdentifier;
use Gdbots\Pbjx\Exception\SchedulerOperationFailed;
use Gdbots\Pbjx\Scheduler\Scheduler;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class DynamoDbScheduler implements Scheduler
{
    /** @var DynamoDbClient */
    private $dynamoDbClient;

    /**
     * The name of the DynamoDb table where jobs are stored.
     *
     * @var string
     */
    private $tableName;

    /** @var SfnClient */
    private $sfnClient;

    /**
     * The Arn of the state machine where jobs are scheduled.
     * @link https://docs.aws.amazon.com/step-functions/latest/dg/how-step-functions-works.html
     *
     * @var string
     */
    private $stateMachineArn;

    /** @var LoggerInterface */
    private $logger;

    /** @var ItemMarshaler */
    private $marshaler;

    /**
     * @param DynamoDbClient  $dynamoDbClient
     * @param string          $tableName
     * @param SfnClient       $sfnClient
     * @param string          $stateMachineArn
     * @param LoggerInterface $logger
     */
    public function __construct(
        DynamoDbClient $dynamoDbClient,
        string $tableName,
        SfnClient $sfnClient,
        string $stateMachineArn,
        ?LoggerInterface $logger = null
    ) {
        $this->dynamoDbClient = $dynamoDbClient;
        $this->tableName = $tableName;
        $this->sfnClient = $sfnClient;
        $this->stateMachineArn = $stateMachineArn;
        $this->logger = $logger ?: new NullLogger();
        $this->marshaler = new ItemMarshaler();
    }

    /**
     * {@inheritdoc}
     */
    public function createStorage(): void
    {
        $table = new SchedulerTable();
        $table->create($this->dynamoDbClient, $this->tableName);
    }

    /**
     * {@inheritdoc}
     */
    public function describeStorage(): string
    {
        $table = new SchedulerTable();
        return $table->describe($this->dynamoDbClient, $this->tableName);
    }

    /**
     * {@inheritdoc}
     */
    public function sendAt(Command $command, int $timestamp, ?string $jobId = null): string
    {
        $ttl = strtotime('+7 days', $timestamp);
        $jobId = $jobId ?: TimeUuidIdentifier::generate()->toString();
        $executionArn = $this->startExecution($timestamp, $jobId);

        try {
            $payload = $this->marshaler->marshal($command);

            // a command will not be sent in the same context as we currently
            // have so unset these fields and let the task handler (a lambda generally)
            // populate these fields right before processing.
            unset($payload['command_id']);
            unset($payload['occurred_at']);
            unset($payload['expected_etag']);
            unset($payload['ctx_retries']);
            unset($payload['ctx_app']);
            unset($payload['ctx_cloud']);
            unset($payload['ctx_ip']);
            unset($payload['ctx_ua']);

            $params = [
                'TableName'    => $this->tableName,
                'Item'         => [
                    SchedulerTable::HASH_KEY_NAME          => ['S' => $jobId],
                    SchedulerTable::TTL_KEY_NAME           => ['N' => (string)$ttl],
                    SchedulerTable::EXECUTION_ARN_KEY_NAME => ['S' => $executionArn],
                    SchedulerTable::PAYLOAD_KEY_NAME       => ['M' => $payload],
                ],
                'ReturnValues' => 'ALL_OLD',
            ];

            $result = $this->dynamoDbClient->putItem($params);

            if (isset($result['Attributes'])
                && isset($result['Attributes'][SchedulerTable::EXECUTION_ARN_KEY_NAME])
                && isset($result['Attributes'][SchedulerTable::EXECUTION_ARN_KEY_NAME]['S'])
            ) {
                $oldExecutionArn = $result['Attributes'][SchedulerTable::EXECUTION_ARN_KEY_NAME]['S'];
                if ($oldExecutionArn !== $executionArn) {
                    $this->stopExecution($jobId, $oldExecutionArn);
                }
            }
        } catch (\Throwable $t) {
            if ($t instanceof AwsException) {
                $errorName = $t->getAwsErrorCode() ?: ClassUtils::getShortName($t);
                if ('ProvisionedThroughputExceededException' === $errorName) {
                    $code = Code::RESOURCE_EXHAUSTED;
                } else {
                    $code = Code::UNAVAILABLE;
                }
            } else {
                $errorName = ClassUtils::getShortName($t);
                $code = Code::INTERNAL;
            }

            throw new SchedulerOperationFailed(
                sprintf(
                    '%s while putting [%s] into DynamoDb table [%s].',
                    $errorName,
                    $jobId,
                    $this->tableName
                ),
                $code,
                $t
            );
        }

        return $jobId;
    }

    /**
     * {@inheritdoc}
     */
    public function cancelJobs(array $jobIds): void
    {
        // foreach job, run deleteItem in dynamodb
        // using the return values, get its execution arn and stop its execution
        // throw exception if dynamodb delete fails
    }

    /**
     * Starts the execution in the state machine and returns
     * the generated executionArn from AWS.
     *
     * @param int    $timestamp
     * @param string $jobId
     *
     * @return string
     *
     * @throws SchedulerOperationFailed
     */
    private function startExecution(int $timestamp, string $jobId): string
    {
        $sendAt = new \DateTime("@{$timestamp}");
        $input = [
            'send_at' => $sendAt->format(DateUtils::ISO8601_ZULU),
            'job_id'  => $jobId,
        ];

        try {
            $result = $this->sfnClient->startExecution([
                'stateMachineArn' => $this->stateMachineArn,
                'input'           => json_encode($input),
            ]);

            return $result['executionArn'];
        } catch (\Throwable $t) {
            if ($t instanceof AwsException) {
                $errorName = $t->getAwsErrorCode() ?: ClassUtils::getShortName($t);
                switch ($errorName) {
                    case 'ExecutionLimitExceeded':
                        $code = Code::RESOURCE_EXHAUSTED;
                        break;

                    case 'ExecutionAlreadyExists':
                        $code = Code::ALREADY_EXISTS;
                        break;

                    case 'InvalidArn':
                    case 'InvalidExecutionInput':
                    case 'InvalidName':
                        $code = Code::INVALID_ARGUMENT;
                        break;

                    case 'StateMachineDoesNotExist':
                    case 'StateMachineDeleting':
                        $code = Code::NOT_FOUND;
                        break;

                    default:
                        $code = Code::UNAVAILABLE;
                        break;
                }
            } else {
                $errorName = ClassUtils::getShortName($t);
                $code = Code::INTERNAL;
            }

            throw new SchedulerOperationFailed(
                sprintf(
                    '%s while adding to state machine [%s] for job_id [%s].',
                    $errorName,
                    $this->stateMachineArn,
                    $jobId
                ),
                $code,
                $t
            );
        }
    }

    /**
     * @param string $jobId
     * @param string $executionArn
     */
    private function stopExecution(string $jobId, string $executionArn): void
    {
        try {
            $this->sfnClient->stopExecution([
                'executionArn' => $executionArn,
                'cause'        => 'canceled',
            ]);
        } catch (\Throwable $t) {
            $this->logger->error(
                'Failed to stopExecution of [{execution_arn}] for [{job_id}].',
                [
                    'exception'     => $t,
                    'execution_arn' => $executionArn,
                    'job_id'        => $jobId,
                ]
            );
        }
    }
}
