<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;

class TransportExceptionEvent extends TransportEvent
{
    protected \Throwable $exception;

    public function __construct(string $transportName, Message $message, \Throwable $exception)
    {
        parent::__construct($transportName, $message);
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
