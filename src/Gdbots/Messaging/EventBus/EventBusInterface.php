<?php

namespace Gdbots\Messaging\EventBus;

interface EventBusInterface
{
    /**
     * Publishes events to all subscribers.
     *
     * @param EventInterface $event
     * @return void
     *
     * @throws \Exception
     */
    public function publish(EventInterface $event);

    /**
     * Processes an event directly.  DO NOT use this method in
     * the application as this is intended for the consumers
     * and workers of the messaging system.
     *
     * @param EventInterface $event
     * @return void
     *
     * @throws \Exception
     */
    public function receiveEvent(EventInterface $event);
}