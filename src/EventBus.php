<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface EventBus
{
    /**
     * Publishes events to all subscribers.
     *
     * @param Message $event
     *
     * @throws GdbotsPbjxException
     * @throws \Throwable
     */
    public function publish(Message $event): void;

    /**
     * Processes an event directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @param Message $event
     *
     * @throws GdbotsPbjxException
     * @throws \Throwable
     *
     * @internal
     */
    public function receiveEvent(Message $event): void;
}
