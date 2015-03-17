<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;

class TransportEvent extends PbjxEvent
{
    /* @var string */
    protected $transportName;

    /**
     * @param string $transportName
     * @param Message $message
     */
    public function __construct($transportName, Message $message)
    {
        parent::__construct($message);
        $this->transportName = $transportName;
    }

    /**
     * @return string
     */
    public function getTransportName()
    {
        return $this->transportName;
    }
}
