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
     * @param \Exception $exception
     */
    public function __construct(string $transportName, Message $message, \Exception $exception)
    {
        parent::__construct($transportName, $message);
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
