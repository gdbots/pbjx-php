<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Mixin\Command;
use Gdbots\Pbj\Mixin\DomainEvent;
use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use React\Promise\ExtendedPromiseInterface;

interface Pbjx
{
    /**
     * Processes a command asynchronously.
     *
     * @param Command $command
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function send(Command $command);

    /**
     * Publishes events to all subscribers.
     *
     * @param DomainEvent $domainEvent
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function publish(DomainEvent $domainEvent);

    /**
     * Processes a request and returns a Promise for the result.
     *
     * @param Request $request
     * @return ExtendedPromiseInterface
     */
    public function request(Request $request);
}
