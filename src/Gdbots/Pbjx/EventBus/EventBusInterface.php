<?php

namespace Gdbots\Pbjx\EventBus;

interface EventBusInterface
{
    /**
     * Publishes events to all subscribers.
     *
     * @param DomainEventInterface $event
     * @return void
     *
     * @throws \Exception
     */
    public function publish(DomainEventInterface $event);

    /**
     * Processes an event directly.  DO NOT use this method in
     * the application as this is intended for the consumers
     * and workers of the messaging system.
     *
     * @param DomainEventInterface $event
     * @return void
     *
     * @throws \Exception
     */
    public function receiveEvent(DomainEventInterface $event);
}