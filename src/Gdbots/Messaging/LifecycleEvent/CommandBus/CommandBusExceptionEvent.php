<?php

namespace Gdbots\Messaging\LifecycleEvent\CommandBus;

use Gdbots\Messaging\CommandBus\CommandInterface;

class CommandBusExceptionEvent extends CommandBusEvent
{
    /* @var \Exception */
    protected $exception;

    /**
     * @param CommandInterface $command
     * @param \Exception $e
     */
    public function __construct(CommandInterface $command, \Exception $e)
    {
        parent::__construct($command);
        $this->exception = $e;
    }

    /**
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
    }
}