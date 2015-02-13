<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface CommandBusReceiver extends CommandBus
{
    /**
     * Processes a command directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @param Command $command
     * @throws GdbotsPbjxException
     */
    public function receiveCommand(Command $command);
}
