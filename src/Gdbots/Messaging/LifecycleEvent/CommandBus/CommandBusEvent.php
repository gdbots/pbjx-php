<?php

namespace Gdbots\Messaging\LifecycleEvent\CommandBus;

use Gdbots\Messaging\CommandBus\CommandInterface;
use Gdbots\Messaging\LifecycleEvent\MessagingEvent;

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