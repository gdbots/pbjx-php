<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;

class BusExceptionEvent extends PbjxEvent
{
    protected \Throwable $exception;

    public function __construct(Message $message, \Throwable $exception)
    {
        parent::__construct($message);
        $this->exception = $exception;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function supportsRecursion(): bool
    {
        return false;
    }
}
