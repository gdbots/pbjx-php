<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Mixin\Command;
use Gdbots\Pbj\Mixin\DomainEvent;
use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbj\Mixin\Response;
use Gdbots\Pbj\Serializer\PhpSerializer;
use Gdbots\Pbj\Serializer\Serializer;
use Gdbots\Pbjx\Router;
use Gdbots\Pbjx\ServiceLocator;

class GearmanConsumer
{
    /** @var \GearmanClient */
    protected $client;

    /** @var Serializer */
    protected $serializer;

    /**
     * The channels this consumer is listening to.  In gearman,
     * this is the function name.
     *
     * @see \GearmanWorker::addFunction
     *
     * @var array
     */
    protected $channels = [];

    /**
     * @param ServiceLocator $locator
     * @param Router $router
     */
    public function __construct(ServiceLocator $locator, $servers = [], $channels = [], $timeout = 5000)
    {
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
        $this->getClient()->doBackground($channel, $workload, $command->getCommandId());
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
        $this->getClient()->doBackground($channel, $workload, $domainEvent->getEventId());
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
        return parent::doSendRequest($request);
    }

    /**
     * @return \GearmanClient
     */
    protected function getClient()
    {
        if (null === $this->client) {
            $client = new \GearmanClient();
            $client->addServer();
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
}
