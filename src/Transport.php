<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Command;
use Gdbots\Pbj\DomainEvent;
use Gdbots\Pbj\Request;
use Gdbots\Pbj\Response;
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
