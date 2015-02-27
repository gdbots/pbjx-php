<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbj\Extension\Response;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

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
     * Processes a request and returns the response from the handler.
     *
     * @param Request $request
     * @return Response
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function request(Request $request);
}
