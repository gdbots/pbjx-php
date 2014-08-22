<?php

namespace Gdbots\Messaging\LifecycleEvent\Transport;

use Gdbots\Messaging\CommandBus\CommandInterface;
use Gdbots\Messaging\EventBus\DomainEventInterface;
use Gdbots\Messaging\LifecycleEvent\MessagingEvent;
use Gdbots\Messaging\MessageInterface;
use Gdbots\Messaging\RequestBus\RequestInterface;

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