<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;

class TransportExceptionEvent extends TransportEvent
{
    /** @var \Exception */
    protected $exception;

    /**
     * @param string     $transportName
     * @param Message    $message
     * @param \Throwable $exception
     */
    public function __construct(string $transportName, Message $message, \Throwable $exception)
    {
        parent::__construct($transportName, $message);
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
