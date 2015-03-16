<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Mixin\Command;
use Gdbots\Pbj\Mixin\DomainEvent;
use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbj\Mixin\Response;

class InMemoryTransport extends AbstractTransport
{
    /**
     * Processes the command in memory synchronously.
     *
     * @param Command $command
     * @throws \Exception
     */
    protected function doSendCommand(Command $command)
    {
        $this->locator->getCommandBus()->receiveCommand($command);
    }

    /**
     * Processes the domain event in memory synchronously.
     *
     * @param DomainEvent $domainEvent
     * @throws \Exception
     */
    protected function doSendEvent(DomainEvent $domainEvent)
    {
        $this->locator->getEventBus()->receiveEvent($domainEvent);
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
        return $this->locator->getRequestBus()->receiveRequest($request);
    }
}
