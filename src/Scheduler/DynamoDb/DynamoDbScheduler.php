<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Scheduler\DynamoDb;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Exception\AwsException;
use Aws\Sfn\SfnClient;
use Gdbots\Pbj\Marshaler\DynamoDb\ItemMarshaler;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\ClassUtil;
use Gdbots\Pbj\WellKnown\TimeUuidIdentifier;
use Gdbots\Pbjx\Event\EnrichContextEvent;
use Gdbots\Pbjx\Exception\SchedulerOperationFailed;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Pbjx\Scheduler\Scheduler;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

class DynamoDbScheduler implements Scheduler
{
    /**
     * @link  https://en.wikipedia.org/wiki/ISO_8601
     * @const string
     */
    const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    protected DynamoDbClient $dynamoDbClient;

    /**
     * The name of the DynamoDb table where jobs are stored.
     *
     * @var string
     */
    protected string $tableName;
    protected SfnClient $sfnClient;

    /**
     * The Arn of the state machine where jobs are scheduled.
     * @link https://docs.aws.amazon.com/step-functions/latest/dg/how-step-functions-works.html
     *
     * @var string
     */
    protected string $stateMachineArn;
    protected EventDispatcher $dispatcher;
    protected LoggerInterface $logger;
    protected ItemMarshaler $marshaler;

    public function __construct(
        DynamoDbClient $dynamoDbClient,
        string $tableName,
        SfnClient $sfnClient,
        string $stateMachineArn,
        EventDispatcher $dispatcher,
        ?LoggerInterface $logger = null
    ) {
        $this->dynamoDbClient = $dynamoDbClient;
        $this->tableName = $tableName;
        $this->sfnClient = $sfnClient;
        $this->stateMachineArn = $stateMachineArn;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger ?: new NullLogger();
        $this->marshaler = new ItemMarshaler();
        $this->marshaler->skipValidation(true);
    }

    public function createStorage(array $context = []): void
    {
        $context = $this->enrichContext(__FUNCTION__, $context);
        $table = new SchedulerTable();
        $table->create($this->dynamoDbClient, $this->getTableName($context));
    }

    public function describeStorage(array $context = []): string
    {
        $context = $this->enrichContext(__FUNCTION__, $context);
        $table = new SchedulerTable();
        return $table->describe($this->dynamoDbClient, $this->getTableName($context));
    }

    public function sendAt(Message $command, int $timestamp, ?string $jobId = null, array $context = []): string
    {
        $context['causator'] = $command;
        $context = $this->enrichContext(__FUNCTION__, $context);
        $ttl = strtotime('+7 days', $timestamp);
        $jobId = $jobId ?: TimeUuidIdentifier::generate()->toString();
        $stateMachineArn = $this->getStateMachineArn($context);
        $tableName = $this->getTableName($context);
        $executionArn = $this->startExecution($stateMachineArn, $timestamp, $jobId);

        try {
            $command->freeze();
            $payload = $this->marshaler->marshal($command);

            // a command will not be sent in the same context as we currently
            // have so unset these fields and let the task handler (a lambda generally)
            // populate these fields right before processing.
            unset($payload['command_id']);
            unset($payload['occurred_at']);
            unset($payload['expected_etag']);
            // unset($payload['ctx_retries']);
            unset($payload['ctx_correlator_ref']);
            unset($payload['ctx_app']);
            unset($payload['ctx_cloud']);
            unset($payload['ctx_ip']);
            unset($payload['ctx_ipv6']);
            unset($payload['ctx_ua']);

            $params = [
                'TableName'    => $tableName,
                'Item'         => [
                    SchedulerTable::HASH_KEY_NAME          => ['S' => $jobId],
                    SchedulerTable::SEND_AT_KEY_NAME       => ['N' => (string)$timestamp],
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
                    $this->stopExecution($oldExecutionArn, $jobId, $context);
                }
            }
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

            throw new SchedulerOperationFailed(
                sprintf(
                    '%s while putting [%s] into DynamoDb table [%s].',
                    $errorName,
                    $jobId,
                    $tableName
                ),
                $code,
                $e
            );
        }

        return $jobId;
    }

    public function cancelJobs(array $jobIds, array $context = []): void
    {
        $context = $this->enrichContext(__FUNCTION__, $context);
        $tableName = $this->getTableName($context);

        foreach ($jobIds as $jobId) {
            try {
                $result = $this->dynamoDbClient->deleteItem([
                    'TableName'    => $tableName,
                    'Key'          => [SchedulerTable::HASH_KEY_NAME => ['S' => $jobId]],
                    'ReturnValues' => 'ALL_OLD',
                ]);

                if (isset($result['Attributes'])
                    && isset($result['Attributes'][SchedulerTable::EXECUTION_ARN_KEY_NAME])
                    && isset($result['Attributes'][SchedulerTable::EXECUTION_ARN_KEY_NAME]['S'])
                ) {
                    $executionArn = $result['Attributes'][SchedulerTable::EXECUTION_ARN_KEY_NAME]['S'];
                    $this->stopExecution($executionArn, $jobId, $context);
                }
            } catch (\Throwable $e) {
                if ($e instanceof AwsException) {
                    $errorName = $e->getAwsErrorCode() ?: ClassUtil::getShortName($e);
                    if ('ResourceNotFoundException' === $errorName) {
                        // if it's already deleted/canceled, it's fine
                        continue;
                    } else if ('ProvisionedThroughputExceededException' === $errorName) {
                        $code = Code::RESOURCE_EXHAUSTED;
                    } else {
                        $code = Code::UNAVAILABLE;
                    }
                } else {
                    $errorName = ClassUtil::getShortName($e);
                    $code = Code::INTERNAL;
                }

                throw new SchedulerOperationFailed(
                    sprintf(
                        '%s while deleting [%s] from DynamoDb table [%s].',
                        $errorName,
                        $jobId,
                        $tableName
                    ),
                    $code,
                    $e
                );
            }
        }
    }

    /**
     * Starts the execution in the state machine and returns
     * the generated executionArn from AWS.
     *
     * @param string $stateMachineArn
     * @param int    $timestamp
     * @param string $jobId
     *
     * @return string
     *
     * @throws SchedulerOperationFailed
     */
    protected function startExecution(string $stateMachineArn, int $timestamp, string $jobId): string
    {
        $start = new \DateTime('now', new \DateTimeZone('UTC'));
        $sendAt = new \DateTime("@{$timestamp}");
        $span = (int)$start->diff($sendAt)->format('%a');

        $input = ['job_id' => $jobId];

        /*
         * AWS Step Functions have a one year maximum execution time.
         * Rather than artificially limit our sendAt dates to 1 year
         * in the future we'll just restart the executions when we
         * encounter this scenario.
         */
        if ($span < 365) {
            $input['send_at'] = $sendAt->format(self::DATE_FORMAT);
        } else {
            // just a skosh behind 1 year to fly under the radar
            $start->modify('+364 days');
            $input['resend_at'] = [];

            do {
                $input['resend_at'][] = $start->format(self::DATE_FORMAT);
                $start->modify('+364 days');
            } while ($start < $sendAt);

            // the original sendAt is the last one to "resend"
            $input['resend_at'][] = $sendAt->format(self::DATE_FORMAT);
            $input['send_at'] = array_shift($input['resend_at']);
        }

        try {
            $result = $this->sfnClient->startExecution([
                'stateMachineArn' => $stateMachineArn,
                'input'           => json_encode($input),
            ]);

            return $result['executionArn'];
        } catch (\Throwable $e) {
            if ($e instanceof AwsException) {
                $errorName = $e->getAwsErrorCode() ?: ClassUtil::getShortName($e);
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
                $errorName = ClassUtil::getShortName($e);
                $code = Code::INTERNAL;
            }

            throw new SchedulerOperationFailed(
                sprintf(
                    '%s while adding to state machine [%s] for job_id [%s].',
                    $errorName,
                    $stateMachineArn,
                    $jobId
                ),
                $code,
                $e
            );
        }
    }

    /**
     * I know you hurtin' and worryin', I can feel it on you,
     * but you oughta quit on it now. Because I want it over
     * and done. I do. I'm tired, boss.
     *
     * @param string $executionArn
     * @param string $jobId
     * @param array  $context
     */
    protected function stopExecution(string $executionArn, string $jobId, array $context): void
    {
        try {
            $this->sfnClient->stopExecution([
                'executionArn' => $executionArn,
                'cause'        => 'canceled',
            ]);
        } catch (\Throwable $e) {
            if (false !== strpos($e->getMessage(), 'ExecutionDoesNotExist')) {
                return;
            }

            $this->logger->error(
                'Failed to stopExecution of [{execution_arn}] for [{job_id}].',
                [
                    'exception'     => $e,
                    'execution_arn' => $executionArn,
                    'job_id'        => $jobId,
                ]
            );
        }
    }

    protected function enrichContext(string $operation, array $context): array
    {
        if (isset($context['already_enriched'])) {
            return $context;
        }

        $event = new EnrichContextEvent('scheduler', $operation, $context);
        $context = $this->dispatcher->dispatch($event, PbjxEvents::ENRICH_CONTEXT)->all();
        $context['already_enriched'] = true;
        return $context;
    }

    /**
     * Override to provide your own logic which determines which
     * state machine arn to use.
     *
     * @param array $context
     *
     * @return string
     */
    protected function getStateMachineArn(array $context): string
    {
        return $context['state_machine_arn'] ?? $this->stateMachineArn;
    }

    /**
     * Override to provide your own logic which determines which
     * table name to use.
     *
     * @param array $context
     *
     * @return string
     */
    protected function getTableName(array $context): string
    {
        return $context['table_name'] ?? $this->tableName;
    }
}
