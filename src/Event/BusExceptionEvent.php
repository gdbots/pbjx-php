<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;

class BusExceptionEvent extends PbjxEvent
{
    /** @var \Exception */
    protected $exception;

    /**
     * @param Message    $message
     * @param \Throwable $exception
     */
    public function __construct(Message $message, \Throwable $exception)
    {
        parent::__construct($message);
        $this->exception = $exception;
    }

    /**
     * @return \Throwable
     */
    public function getException(): \Throwable
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
