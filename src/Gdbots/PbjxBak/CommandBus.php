<?php

namespace Gdbots\PbjxBack;

use Gdbots\Pbj\Extension\Command;

interface CommandBus
{
    /**
     * Processes a command asynchronously.
     *
     * @param Command $command
     * @throws \Exception
     */
    public function send(Command $command);
}