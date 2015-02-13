<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface EventBusReceiver extends EventBus
{
    /**
     * Processes an event directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @param DomainEvent $event
     * @throws GdbotsPbjxException
     */
    public function receiveEvent(DomainEvent $event);
}
