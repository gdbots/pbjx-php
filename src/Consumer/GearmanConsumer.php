<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Consumer;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Pbjx\Transport\GearmanRouter;
use Gdbots\Pbjx\Transport\TransportEnvelope;
use Psr\Log\LoggerInterface;

final class GearmanConsumer extends AbstractConsumer
{
    /** @var \GearmanWorker */
    private $worker;

    /**
     * The channels this consumer is listening to.  In gearman, this is the function name.
     * If the supplied array is empty the router default channels are used.
     *
     * @see Router
     * @see \GearmanWorker::addFunction
     * @var array
     */
    private $channels = [];

    /**
     * @link http://php.net/manual/en/gearmanclient.addserver.php
     * @var array
     */
    private $servers = [];

    /**
     * @link http://php.net/manual/en/gearmanworker.settimeout.php
     * @var int
     */
    private $timeout = 5000;

    /**
     * @link http://php.net/manual/en/gearmanworker.setid.php
     * @var string
     */
    private $workerId;

    /**
     * @param ServiceLocator  $locator
     * @param string|null     $workerId
     * @param array           $channels
     * @param array           $servers format [['host' => '127.0.0.1', 'port' => 4730]]
     * @param int             $timeout milliseconds
     * @param LoggerInterface $logger
     */
    public function __construct(
        ServiceLocator $locator,
        ?string $workerId = null,
        ?array $channels = null,
        ?array $servers = null,
        int $timeout = 5000,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($locator, $logger);
        $this->workerId = $workerId;
        $this->channels = $channels
            ?: [
                GearmanRouter::DEFAULT_COMMAND_CHANNEL,
                GearmanRouter::DEFAULT_EVENT_CHANNEL,
                GearmanRouter::DEFAULT_REQUEST_CHANNEL,
            ];
        $this->servers = $servers;
        $this->timeout = NumberUtils::bound($timeout, 100, 10000);
    }

    /**
     * Creates a gearman worker, sets the timeout and adds the servers.
     * At least one server must connect successfully otherwise an exception is thrown.
     *
     * @throws \GearmanException
     */
    protected function setup(): void
    {
        if (null === $this->worker) {
            $worker = new \GearmanWorker();
            $worker->setTimeout($this->timeout);

            if (empty($this->servers)) {
                try {
                    // by default we add the local machine
                    if (!$worker->addServer()) {
                        throw new \GearmanException(
                            'GearmanWorker::addServer returned false.',
                            GEARMAN_COULD_NOT_CONNECT
                        );
                    }
                } catch (\Exception $e) {
                    throw new \GearmanException(
                        'Unable to add local server 127.0.0.1:4730.  ' . $e->getMessage(),
                        GEARMAN_COULD_NOT_CONNECT
                    );
                }
            } else {
                shuffle($this->servers);
                $added = 0;
                foreach ($this->servers as $server) {
                    $host = isset($server['host']) ? $server['host'] : '127.0.0.1';
                    $port = (int)isset($server['port']) ? $server['port'] : 4730;
                    try {
                        if ($worker->addServer($host, $port)) {
                            $added++;
                        }
                    } catch (\Exception $e) {
                        // do nothing, yet.
                    }
                }

                if (0 === $added) {
                    throw new \GearmanException(
                        sprintf('Unable to add any of these servers: %s', json_encode($this->servers)),
                        GEARMAN_COULD_NOT_CONNECT
                    );
                }
            }

            $worker->addOptions(GEARMAN_WORKER_NON_BLOCKING);
            $worker->addOptions(GEARMAN_WORKER_GRAB_UNIQ);
            if (null !== $this->workerId && is_callable([$worker, 'setId'])) {
                $worker->setId($this->workerId);
            }
            $this->worker = $worker;

            shuffle($this->channels);
            foreach ($this->channels as $channel) {
                $this->worker->addFunction($channel, [$this, 'handleJob']);
            }
        }
    }

    /**
     * Runs the gearman worker process.
     *
     * @link http://php.net/manual/en/gearmanworker.work.php
     */
    protected function work(): void
    {
        if (@$this->worker->work()
            || $this->worker->returnCode() == GEARMAN_IO_WAIT
            || $this->worker->returnCode() == GEARMAN_NO_JOBS
        ) {
            if ($this->worker->returnCode() == GEARMAN_SUCCESS) {
                return;
            }

            if (!@$this->worker->wait()) {
                if ($this->worker->returnCode() == GEARMAN_NO_ACTIVE_FDS) {
                    sleep(5);
                }
            }
        }

        // 0  = GEARMAN_SUCCESS
        // 47 = GEARMAN_TIMEOUT
        // 7  = GEARMAN_NO_ACTIVE_FDS
        // 14 = GEARMAN_LOST_CONNECTION
        // 26 = GEARMAN_COULD_NOT_CONNECT
        echo $this->worker->returnCode().PHP_EOL;
    }

    /**
     * @param \GearmanJob $job
     *
     * @return string
     *
     * @throws \Exception
     */
    public function handleJob(\GearmanJob $job): ?string
    {
        $this->logger->debug(
            sprintf(
                'Handling job [%s] with id [%s] on channel [%s], worker id [%s].',
                $job->handle(),
                $job->unique(),
                $job->functionName(),
                $this->workerId
            )
        );

        $dispatcher = $this->locator->getDispatcher();

        try {
            $envelope = TransportEnvelope::fromString($job->workload());
            $dispatcher->dispatch(PbjxEvents::CONSUMER_BEFORE_HANDLE);
            $result = $this->handleMessage($envelope->getMessage());
            $dispatcher->dispatch(PbjxEvents::CONSUMER_AFTER_HANDLE);

            if ($result instanceof Message) {
                // if the request is a replay, does that imply the response is too?
                return (new TransportEnvelope($result, $envelope->getSerializerUsed()))->toString();
            }
        } catch (\Exception $e) {
            $job->sendFail();
            $this->logger->error(
                sprintf(
                    '%s::Consumer [%s] failed to handle job [{job_handle}::{job_id}].',
                    ClassUtils::getShortName($e),
                    $this->consumerName
                ),
                [
                    'exception'             => $e,
                    'consumer'              => $this->consumerName,
                    'gearman_function_name' => $job->functionName(),
                    'gearman_worker_id'     => $this->workerId,
                    'job_handle'            => $job->handle(),
                    'job_id'                => $job->unique(),
                    'job_workload'          => $job->workload(),
                ]
            );

            throw $e;
        }

        return null;
    }

    /**
     * Unregisters all functions from the gearman worker and then nullifies the worker.
     */
    protected function teardown(): void
    {
        if ($this->worker) {
            @$this->worker->unregisterAll();
            $this->worker = null;
        }
    }
}
