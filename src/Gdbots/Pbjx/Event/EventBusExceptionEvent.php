<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Extension\DomainEvent;

class EventBusExceptionEvent extends EventBusEvent
{
    /** @var \Exception */
    protected $exception;

    /**
     * @param DomainEvent $domainEvent
     * @param \Exception $exception
     */
    public function __construct(DomainEvent $domainEvent, \Exception $exception)
    {
        parent::__construct($domainEvent);
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
