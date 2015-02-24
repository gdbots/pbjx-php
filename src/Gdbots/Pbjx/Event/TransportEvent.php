<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbj\Message;

class TransportEvent extends PbjxEvent
{
    /** @var Message */
    protected $message;

    /* @var string */
    protected $transportName;

    /**
     * @param string $transportName
     * @param Message $message
     */
    public function __construct($transportName, Message $message)
    {
        $this->transportName = $transportName;
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getTransportName()
    {
        return $this->transportName;
    }

    /**
     * @return Message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return boolean
     */
    public function isCommandMessage()
    {
        return $this->message instanceof Command;
    }

    /**
     * @return boolean
     */
    public function isDomainEventMessage()
    {
        return $this->message instanceof DomainEvent;
    }

    /**
     * @return boolean
     */
    public function isRequestMessage()
    {
        return $this->message instanceof Request;
    }
}
