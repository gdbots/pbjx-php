<?php

namespace Gdbots\PbjxBack;

use Gdbots\Pbj\Extension\Command;

interface CommandBusReceiver
{
    /**
     * Processes a command directly.  DO NOT use this method in the
     * application as this is intended for the consumers and workers
     * of the messaging system.
     *
     * @param Command $command
     * @throws \Exception
     */
    public function receiveCommand(Command $command);
}
