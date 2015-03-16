<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Mixin\DomainEvent;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface EventBus
{
    /**
     * Publishes events to all subscribers.
     *
     * @param DomainEvent $domainEvent
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function publish(DomainEvent $domainEvent);

    /**
     * Processes an event directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @param DomainEvent $domainEvent
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function receiveEvent(DomainEvent $domainEvent);
}
