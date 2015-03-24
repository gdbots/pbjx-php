<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Message;
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
     * @param ServiceLocator $locator
     * @param Router $router
     */
    public function __construct(ServiceLocator $locator, Router $router = null)
    {
        parent::__construct($locator);
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
        $this->validateReturnCode($client, $channel, $command);
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
        $this->validateReturnCode($client, $channel, $domainEvent);
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
        $this->validateReturnCode($client, $channel, $request);
        return $this->getSerializer()->deserialize($result);
    }

    /**
     * @return \GearmanClient
     */
    protected function getClient()
    {
        if (null === $this->client) {
            $client = new \GearmanClient();
            $client->addServer();
            // todo: move timeout configurable
            $client->setTimeout(200);
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
     * todo: make these into custom exceptions
     *
     * @param \GearmanClient $client
     * @param string $channel
     * @param Message $message
     *
     * @throws \Exception
     */
    protected function validateReturnCode(\GearmanClient $client, $channel, Message $message)
    {
        switch ($client->returnCode()) {
            case GEARMAN_SUCCESS:
            case GEARMAN_WORK_DATA:
            case GEARMAN_WORK_STATUS:
                break;

            case GEARMAN_TIMEOUT:
                throw new \Exception("Timeout reached, no available workers", $client->returnCode());

            case GEARMAN_ERRNO:
                throw new \Exception('Unknown error (' . $client->getErrno() . '): ' . $client->error());

            case GEARMAN_WORK_FAIL:
                throw new \Exception("Failed", $client->returnCode());

            case GEARMAN_LOST_CONNECTION:
            case GEARMAN_COULD_NOT_CONNECT:
                throw new \Exception("Lost connection", $client->returnCode());

            default:
                throw new \Exception("RET: " . $client->returnCode(), $client->returnCode());
        }
    }
}
