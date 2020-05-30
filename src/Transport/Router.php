<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Message;

/**
 * A router is used by transports to determine which channel a message
 * should be sent on.  This is a one-to-one mapping and is ideally
 * idempotent so that given the same message it always ends up on
 * the same channel.
 */
interface Router
{
    public function forCommand(Message $command): string;

    public function forEvent(Message $event): string;

    public function forRequest(Message $request): string;
}
