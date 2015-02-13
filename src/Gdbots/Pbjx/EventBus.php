<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface EventBus
{
    /**
     * Publishes events to all subscribers.
     *
     * @param DomainEvent $event
     * @throws GdbotsPbjxException
     */
    public function publish(DomainEvent $event);
}
