<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface Transport
{
    /**
     * Sends a command via the transport.
     *
     * @param Command $command
     * @throws GdbotsPbjxException
     */
    public function sendCommand(Command $command);

    /**
     * Sends an event via the transport.
     *
     * @param DomainEvent $event
     * @throws GdbotsPbjxException
     */
    //public function sendEvent(DomainEvent $event);

    /**
     * Sends a request via the transport.
     *
     * @param Request $request
     * @return mixed
     * @throws GdbotsPbjxException
     */
    //public function sendRequest(Request $request);
}
