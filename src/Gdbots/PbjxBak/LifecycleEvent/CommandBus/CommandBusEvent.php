<?php

namespace Gdbots\PbjxBack\LifecycleEvent\CommandBus;

use Gdbots\PbjxBack\CommandBus\CommandInterface;
use Gdbots\PbjxBack\LifecycleEvent\PbjxEvent;

class CommandBusEvent extends MessagingEvent
{
    /* @var CommandInterface */
    protected $command;

    /**
     * @param CommandInterface $command
     */
    public function __construct(CommandInterface $command)
    {
        $this->command = $command;
    }

    /**
     * @return CommandInterface
     */
    public function getCommand()
    {
        return $this->command;
    }
}