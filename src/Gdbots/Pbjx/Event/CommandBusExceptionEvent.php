<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Extension\Command;

class CommandBusExceptionEvent extends CommandBusEvent
{
    /** @var \Exception */
    protected $exception;

    /**
     * @param Command $command
     * @param \Exception $exception
     */
    public function __construct(Command $command, \Exception $exception)
    {
        parent::__construct($command);
        $this->exception = $exception;
    }

    /**
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
    }
}
