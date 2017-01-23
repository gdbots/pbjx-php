<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;

class BusExceptionEvent extends PbjxEvent
{
    /** @var \Exception */
    protected $exception;

    /**
     * @param Message    $message
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
    public function getException(): \Exception
    {
        return $this->exception;
    }

    /**
     * @return bool
     */
    public function supportsRecursion(): bool
    {
        return false;
    }
}
