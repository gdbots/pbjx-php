<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbj\Extension\Response;
use Gdbots\Pbjx\Notifier;

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
     * @param Notifier $notifier
     * @return Response
     * @throws \Exception
     */
    protected function doSendRequest(Request $request, Notifier $notifier)
    {
        return $this->locator->getRequestBus()->receiveRequest($request, $notifier);
    }
}
