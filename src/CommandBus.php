<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;

interface CommandBus
{
    /**
     * Processes a command asynchronously.
     *
     * @param Command $command
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function send(Command $command);

    /**
     * Processes a command directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @param Command $command
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function receiveCommand(Command $command);
}
