<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbj\Extension\DomainEvent;

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
}
