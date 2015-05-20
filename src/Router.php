<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\DomainCommand;
use Gdbots\Pbj\DomainEvent;
use Gdbots\Pbj\DomainRequest;

/**
 * A router is used by transports to determine which channel a message
 * should be sent on.  This is a one-to-one mapping and is ideally
 * idempotent so that given the same message it always ends up on
 * the same channel.
 */
interface Router
{
    /**
     * @param DomainCommand $command
     * @return string
     */
    public function forCommand(DomainCommand $command);

    /**
     * @param DomainEvent $domainEvent
     * @return string
     */
    public function forEvent(DomainEvent $domainEvent);

    /**
     * @param DomainRequest $request
     * @return string
     */
    public function forRequest(DomainRequest $request);
}
