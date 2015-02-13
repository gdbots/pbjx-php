<?php

namespace Gdbots\PbjxBack\LifecycleEvent\CommandBus;

use Gdbots\PbjxBack\CommandBus\CommandInterface;

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