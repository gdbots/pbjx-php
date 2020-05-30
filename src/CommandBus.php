<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface CommandBus
{
    /**
     * Processes a command asynchronously.
     *
     * @param Message $command
     *
     * @throws GdbotsPbjxException
     * @throws \Throwable
     */
    public function send(Message $command): void;

    /**
     * Processes a command directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @param Message $command
     *
     * @throws GdbotsPbjxException
     * @throws \Throwable
     *
     * @internal
     */
    public function receiveCommand(Message $command): void;
}
