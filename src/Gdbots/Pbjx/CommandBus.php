<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface CommandBus
{
    /**
     * Processes a command asynchronously.
     *
     * @param Command $command
     * @throws GdbotsPbjxException
     */
    public function send(Command $command);
}
