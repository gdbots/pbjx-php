<?php

namespace Gdbots\PbjxBack\LifecycleEvent\Transport;

use Gdbots\PbjxBack\CommandBus\CommandInterface;
use Gdbots\PbjxBack\EventBus\DomainEventInterface;
use Gdbots\PbjxBack\LifecycleEvent\PbjxEvent;
use Gdbots\PbjxBack\MessageInterface;
use Gdbots\PbjxBack\RequestBus\RequestInterface;

class TransportEvent extends MessagingEvent
{
    /* @var MessageInterface */
    protected $message;

    /* @var string */
    protected $transportName;

    /**
     * @param string $transportName
     * @param MessageInterface $message
     */
    public function __construct($transportName, MessageInterface $message)
    {
        $this->message = $message;
        $this->transportName = $transportName;
    }

    /**
     * @return MessageInterface
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return string
     */
    public function getTransportName()
    {
        return $this->transportName;
    }

    /**
     * @return boolean
     */
    public function isCommandMessage()
    {
        return $this->message instanceof CommandInterface;
    }

    /**
     * @return boolean
     */
    public function isDomainEventMessage()
    {
        return $this->message instanceof DomainEventInterface;
    }

    /**
     * @return boolean
     */
    public function isRequestMessage()
    {
        return $this->message instanceof RequestInterface;
    }
}