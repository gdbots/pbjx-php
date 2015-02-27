<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Extension\DomainEvent;

class EventBusEvent extends PbjxEvent
{
    /** @var DomainEvent */
    protected $domainEvent;

    /**
     * @param DomainEvent $domainEvent
     */
    public function __construct(DomainEvent $domainEvent)
    {
        $this->domainEvent = $domainEvent;
    }

    /**
     * @return DomainEvent
     */
    public function getDomainEvent()
    {
        return $this->domainEvent;
    }
}
