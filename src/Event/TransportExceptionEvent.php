<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;

class TransportExceptionEvent extends TransportEvent
{
    /** @var \Exception */
    protected $exception;

    /**
     * @param string $transportName
     * @param Message $message
     * @param \Exception $exception
     */
    public function __construct($transportName, Message $message, \Exception $exception)
    {
        parent::__construct($transportName, $message);
        $this->exception = $exception;
    }

    /**
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @return bool
     */
    public function supportsRecursion()
    {
        return false;
    }
}
