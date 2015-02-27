<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbj\Extension\Response;
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
    //public function sendRequest(Request $request);
}
