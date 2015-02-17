<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Extension\Command;

class CommandBusEvent extends PbjxEvent
{
    /** @var Command */
    protected $command;

    /**
     * @param Command $command
     */
    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * @return Command
     */
    public function getCommand()
    {
        return $this->command;
    }
}
