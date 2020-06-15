<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;

class TransportEvent extends PbjxEvent
{
    protected string $transportName;

    public function __construct(string $transportName, Message $message)
    {
        parent::__construct($message);
        $this->transportName = $transportName;
    }

    public function getTransportName(): string
    {
        return $this->transportName;
    }

    public function supportsRecursion(): bool
    {
        return false;
    }
}
