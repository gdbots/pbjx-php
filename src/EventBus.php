<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Schemas\Pbjx\Event\Event;

interface EventBus
{
    /**
     * Publishes events to all subscribers.
     *
     * @param Event $event
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function publish(Event $event);

    /**
     * Processes an event directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @param Event $event
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function receiveEvent(Event $event);
}
