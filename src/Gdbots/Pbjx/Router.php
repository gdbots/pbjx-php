<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Mixin\Command;
use Gdbots\Pbj\Mixin\DomainEvent;
use Gdbots\Pbj\Mixin\Request;

/**
 * A router is used by transports to determine which channel a message
 * should be sent on.  This is a one-to-one mapping and is ideally
 * idempotent so that given the same message it always ends up on
 * the same channel.
 */
interface Router
{
    const DEFAULT_COMMAND_CHANNEL = 'commands';
    const DEFAULT_EVENT_CHANNEL = 'events';
    const DEFAULT_REQUEST_CHANNEL = 'requests';

    /**
     * @param Command $command
     * @return string
     */
    public function forCommand(Command $command);

    /**
     * @param DomainEvent $domainEvent
     * @return string
     */
    public function forEvent(DomainEvent $domainEvent);

    /**
     * @param Request $request
     * @return string
     */
    public function forRequest(Request $request);
}
