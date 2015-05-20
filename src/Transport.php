<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\DomainCommand;
use Gdbots\Pbj\DomainEvent;
use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbj\DomainResponse;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface Transport
{
    /**
     * Sends a command via the transport.
     *
     * @param DomainCommand $command
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function sendCommand(DomainCommand $command);

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
     * @param DomainRequest $request
     * @return DomainResponse
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function sendRequest(DomainRequest $request);
}
