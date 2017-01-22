<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;

class TransportEvent extends PbjxEvent
{
    /* @var string */
    protected $transportName;

    /**
     * @param string  $transportName
     * @param Message $message
     */
    public function __construct(string $transportName, Message $message)
    {
        parent::__construct($message);
        $this->transportName = $transportName;
    }

    /**
     * @return string
     */
    public function getTransportName(): string
    {
        return $this->transportName;
    }

    /**
     * @return bool
     */
    public function supportsRecursion(): bool
    {
        return false;
    }
}
