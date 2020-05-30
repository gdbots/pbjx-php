<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Message;

interface Transport
{
    public function sendCommand(Message $command): void;

    public function sendEvent(Message $event): void;

    public function sendRequest(Message $request): Message;
}
