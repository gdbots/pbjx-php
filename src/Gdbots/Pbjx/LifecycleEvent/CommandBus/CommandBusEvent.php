<?php

namespace Gdbots\Pbjx\LifecycleEvent\CommandBus;

use Gdbots\Pbjx\CommandBus\CommandInterface;
use Gdbots\Pbjx\LifecycleEvent\PbjxEvent;

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