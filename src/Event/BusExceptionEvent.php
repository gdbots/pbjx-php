<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;

class BusExceptionEvent extends PbjxEvent
{
    /** @var \Exception */
    protected $exception;

    /**
     * @param Message $message
     * @param \Exception $exception
     */
    public function __construct(Message $message, \Exception $exception)
    {
        parent::__construct($message);
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
