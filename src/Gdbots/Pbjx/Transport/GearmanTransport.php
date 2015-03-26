<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\Mixin\Command;
use Gdbots\Pbj\Mixin\DomainEvent;
use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbj\Mixin\Response;
use Gdbots\Pbj\Serializer\PhpSerializer;
use Gdbots\Pbj\Serializer\Serializer;
use Gdbots\Pbjx\Router;
use Gdbots\Pbjx\ServiceLocator;

class GearmanTransport extends AbstractTransport
{
    /** @var \GearmanClient */
    protected $client;

    /** @var Serializer */
    protected $serializer;

    /** @var Router */
    protected $router;

    /**
     * @link http://php.net/manual/en/gearmanclient.addserver.php
     * @var array
     */
    protected $servers = [];

    /**
     * @link http://php.net/manual/en/gearmanclient.settimeout.php
     * @var int
     */
    protected $timeout = 5000;

    /**
     * @param ServiceLocator $locator
     * @param array $servers servers in the format [['host' => '127.0.0.1', 'port' => 4730]]
     * @param int $timeout milliseconds
     * @param Router $router
     */
    public function __construct(ServiceLocator $locator, array $servers = [], $timeout = 5000, Router $router = null)
    {
        parent::__construct($locator);
        $this->servers = $servers;
        $this->timeout = NumberUtils::bound($timeout, 200, 10000);
        $this->router = $router ?: new GearmanRouter();
    }

    /**
     * @see Router::forCommand
     * @see GearmanClient::doBackground
     *
     * @param Command $command
     * @throws \Exception
     */
    protected function doSendCommand(Command $command)
    {
        $workload = $this->getSerializer()->serialize($command);
        $channel = $this->router->forCommand($command);
        $client = $this->getClient();

        @$client->doBackground($channel, $workload, $command->getCommandId());
        $this->validateReturnCode($client, $channel);
    }

    /**
     * @see Router::forEvent
     * @see GearmanClient::doBackground
     *
     * @param DomainEvent $domainEvent
     * @throws \Exception
     */
    protected function doSendEvent(DomainEvent $domainEvent)
    {
        $workload = $this->getSerializer()->serialize($domainEvent);
        $channel = $this->router->forEvent($domainEvent);
        $client = $this->getClient();

        @$client->doBackground($channel, $workload, $domainEvent->getEventId());
        $this->validateReturnCode($client, $channel);
    }

    /**
     * Processes the request in memory synchronously.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    protected function doSendRequest(Request $request)
    {
        $workload = $this->getSerializer()->serialize($request);
        $channel = $this->router->forRequest($request);
        $client = $this->getClient();

        $result = @$client->doNormal($channel, $workload, $request->getRequestId());
        $this->validateReturnCode($client, $channel);
        return $this->getSerializer()->deserialize($result);
    }

    /**
     * Creates a gearman client, sets the timeout and adds the servers.
     * At least one server must connect successfully otherwise an exception is thrown.
     *
     * Gearman's failure to add a server produces an exception with a message of
     * 'Failed to set exception option' which isn't entirely clear but it means you
     * won't be sending any messages.  :)
     *
     * @return \GearmanClient
     * @throws \GearmanException
     */
    protected function getClient()
    {
        if (null === $this->client) {
            $client = new \GearmanClient();
            $client->setTimeout($this->timeout);

            if (empty($this->servers)) {
                try {
                    // by default we add the local machine
                    $client->addServer();
                } catch (\Exception $e) {
                    throw new \GearmanException('Unable to add local server 127.0.0.1:4730.');
                }
            } else {
                shuffle($this->servers);
                $added = 0;
                foreach ($this->servers as $server) {
                    $host = isset($server['host']) ? $server['host'] : '127.0.0.1';
                    $port = (int) isset($server['port']) ? $server['port'] : 4730;
                    try {
                        $client->addServer($host, $port);
                        $added++;
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

            $this->client = $client;
        }

        return $this->client;
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

    /**
     * Checks the return code from gearman and throws an exception if it's a failure.
     *
     * @param \GearmanClient $client
     * @param string $channel
     *
     * @throws \Exception
     */
    protected function validateReturnCode(\GearmanClient $client, $channel)
    {
        switch ($client->returnCode()) {
            case GEARMAN_SUCCESS:
            case GEARMAN_WORK_DATA:
            case GEARMAN_WORK_STATUS:
                return;

            case GEARMAN_TIMEOUT:
                throw new \GearmanException(
                    sprintf(
                        'Code [%s] :: Timeout reached, no available workers for channel [%s].',
                        $client->returnCode(),
                        $channel
                    ), $client->returnCode()
                );

            case GEARMAN_ERRNO:
                throw new \GearmanException(
                    sprintf(
                        'Code [%s] :: Error [%s::%s] on channel [%s].',
                        $client->returnCode(),
                        $client->getErrno(),
                        $client->error(),
                        $channel
                    ), $client->returnCode()
                );

            case GEARMAN_WORK_FAIL:
                throw new \GearmanException(
                    sprintf(
                        'Code [%s] :: Worker failed [%s::%s] on channel [%s].',
                        $client->returnCode(),
                        $client->getErrno(),
                        $client->error(),
                        $channel
                    ), $client->returnCode()
                );

            case GEARMAN_LOST_CONNECTION:
            case GEARMAN_COULD_NOT_CONNECT:
                throw new \GearmanException(
                    sprintf(
                        'Code [%s] :: Lost connection to channel [%s].',
                        $client->returnCode(),
                        $channel
                    ), $client->returnCode()
                );

            default:
                throw new \GearmanException(
                    sprintf(
                        'Code [%s] :: Unspecified error [%s::%s] on channel [%s].',
                        $client->returnCode(),
                        $client->getErrno(),
                        $client->error(),
                        $channel
                    ), $client->returnCode()
                );
        }
    }
}
