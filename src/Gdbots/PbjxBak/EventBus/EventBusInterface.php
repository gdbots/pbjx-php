<?php

namespace Gdbots\PbjxBack;

use Gdbots\Pbj\Extension\DomainEvent;

interface EventBus
{
    /**
     * Publishes events to all subscribers.
     *
     * @param DomainEvent $event
     * @return void
     *
     * @throws \Exception
     */
    public function publish(DomainEvent $event);

    /**
     * Processes an event directly.  DO NOT use this method in
     * the application as this is intended for the consumers
     * and workers of the messaging system.
     *
     * @param DomainEvent $event
     * @throws \Exception
     */
    public function receiveEvent(DomainEvent $event);
}