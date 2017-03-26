<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;

interface EventBus
{
    /**
     * Publishes events to all subscribers.
     *
     * @param Event $event
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function publish(Event $event): void;

    /**
     * Processes an event directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @internal
     *
     * @param Event $event
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function receiveEvent(Event $event): void;
}
