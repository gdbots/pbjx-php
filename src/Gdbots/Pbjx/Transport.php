<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Mixin\Command;
use Gdbots\Pbj\Mixin\DomainEvent;
use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbj\Mixin\Response;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface Transport
{
    /**
     * Sends a command via the transport.
     *
     * @param Command $command
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function sendCommand(Command $command);

    /**
     * Sends an event via the transport.
     *
     * @param DomainEvent $domainEvent
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function sendEvent(DomainEvent $domainEvent);

    /**
     * Sends a request via the transport.
     *
     * @param Request $request
     * @return Response
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function sendRequest(Request $request);
}
