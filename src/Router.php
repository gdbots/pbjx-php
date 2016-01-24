<?php

namespace Gdbots\Pbjx;

use Gdbots\Schemas\Pbj\Command\Command;
use Gdbots\Schemas\Pbj\Event\Event;
use Gdbots\Schemas\Pbj\Request\Request;

/**
 * A router is used by transports to determine which channel a message
 * should be sent on.  This is a one-to-one mapping and is ideally
 * idempotent so that given the same message it always ends up on
 * the same channel.
 */
interface Router
{
    /**
     * @param Command $command
     * @return string
     */
    public function forCommand(Command $command);

    /**
     * @param Event $event
     * @return string
     */
    public function forEvent(Event $event);

    /**
     * @param Request $request
     * @return string
     */
    public function forRequest(Request $request);
}
