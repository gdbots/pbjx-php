<?php

namespace Gdbots\Pbjx\Consumer;

use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\Serializer\PhpSerializer;
use Gdbots\Pbj\Serializer\Serializer;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Pbjx\Router;
use Gdbots\Pbjx\ServiceLocator;
use Psr\Log\LoggerInterface;

/**
 * todo: add setId support for workers
 * @link http://php.net/manual/en/gearmanworker.setid.php
 */
class GearmanConsumer extends AbstractConsumer
{
    /** @var \GearmanWorker */
    protected $worker;

    /** @var Serializer */
    protected $serializer;

    /**
     * The channels this consumer is listening to.  In gearman, this is the function name.
     * If the supplied array is empty the router default channels are used.
     * @see Router
     * @see \GearmanWorker::addFunction
     * @var array
     */
    protected $channels = [];

    /**
     * @link http://php.net/manual/en/gearmanclient.addserver.php
     * @var array
     */
    protected $servers = [];

    /**
     * @link http://php.net/manual/en/gearmanworker.settimeout.php
     * @var int
     */
    protected $timeout = 5000;

    /**
     * @param ServiceLocator $locator
     * @param array $channels
     * @param array $servers format [['host' => '127.0.0.1', 'port' => 4730]]
     * @param int $timeout milliseconds
     * @param LoggerInterface $logger
     */
    public function __construct(
        ServiceLocator $locator,
        array $channels = [],
        array $servers = [],
        $timeout = 5000,
        LoggerInterface $logger = null
    ) {
        parent::__construct($locator, $logger);
        $this->channels = $channels
            ?: [Router::DEFAULT_COMMAND_CHANNEL, Router::DEFAULT_EVENT_CHANNEL, Router::DEFAULT_REQUEST_CHANNEL]
        ;
        $this->servers = $servers;
        $this->timeout = NumberUtils::bound($timeout, 200, 10000);
    }

    /**
     * Creates a gearman worker, sets the timeout and adds the servers.
     * At least one server must connect successfully otherwise an exception is thrown.
     *
     * @throws \GearmanException
     */
    protected function setup()
    {
        if (null === $this->worker) {
            $worker = new \GearmanWorker();
            $worker->setTimeout($this->timeout);

            if (empty($this->servers)) {
                try {
                    // by default we add the local machine
                    if (!$worker->addServer()) {
                        throw new \GearmanException('GearmanWorker::addServer returned false.');
                    }
                } catch (\Exception $e) {
                    throw new \GearmanException('Unable to add local server 127.0.0.1:4730.  ' . $e->getMessage());
                }
            } else {
                shuffle($this->servers);
                $added = 0;
                foreach ($this->servers as $server) {
                    $host = isset($server['host']) ? $server['host'] : '127.0.0.1';
                    $port = (int) isset($server['port']) ? $server['port'] : 4730;
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
                        sprintf('Unable to add any of these servers: %s', json_encode($this->servers))
                    );
                }
            }

            $worker->addOptions(GEARMAN_WORKER_NON_BLOCKING);
            $worker->addOptions(GEARMAN_WORKER_GRAB_UNIQ);
            $this->worker = $worker;

            shuffle($this->channels);
            foreach ($this->channels as $channel) {
                $this->worker->addFunction($channel, array($this, 'handleJob'));
            }
        }
    }

    /**
     * Runs the gearman worker process.
     * @link http://php.net/manual/en/gearmanworker.work.php
     */
    protected function work()
    {
        if (@$this->worker->work() ||
            $this->worker->returnCode() == GEARMAN_IO_WAIT ||
            $this->worker->returnCode() == GEARMAN_NO_JOBS
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
    }

    /**
     * @param \GearmanJob $job
     * @return null|string
     */
    public function handleJob(\GearmanJob $job)
    {
        $this->logger->info(
            sprintf(
                'Handling job [%s] with id [%s] on channel [%s].',
                $job->handle(),
                $job->unique(),
                $job->functionName()
            )
        );

        try {
            $serializer = $this->getSerializer();
            $message = $serializer->deserialize($job->workload());
            $result = $this->handleMessage($message);
            $this->locator->getDispatcher()->dispatch(PbjxEvents::CONSUMER_AFTER_HANDLE_MESSAGE);

            if ($result instanceof Message) {
                return $serializer->serialize($result);
            }

            return null;

        } catch (\Exception $e) {
            $job->sendFail();
            $this->logger->error(
                sprintf(
                    'Failed to handle job [%s] with id [%s] on channel [%s].  %s',
                    $job->handle(),
                    $job->unique(),
                    $job->functionName(),
                    $e->getMessage()
                )
            );
        }

        return null;
    }

    /**
     * Unregisters all functions from the gearman worker and then nullifies the worker.
     */
    protected function teardown()
    {
        if ($this->worker) {
            @$this->worker->unregisterAll();
            $this->worker = null;
        }
    }

    /**
     * @return Serializer
     */
    protected function getSerializer()
    {
        if (null === $this->serializer) {
            $this->serializer = new PhpSerializer();
        }
        return $this->serializer;
    }
}
