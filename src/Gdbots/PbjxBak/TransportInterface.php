<?php

namespace Gdbots\PbjxBack;

use Gdbots\PbjxBack\CommandBus\CommandInterface;
use Gdbots\PbjxBack\EventBus\DomainEventInterface;
use Gdbots\PbjxBack\RequestBus\RequestInterface;

interface TransportInterface
{
    /**
     * Returns the name of the transport.  e.g. memory, gearman, sqs
     *
     * @return string
     */
    public static function getName();

    /**
     * Sends a command via the transport.
     *
     * @param CommandInterface $command
     * @throws \Exception
     */
    public function sendCommand(CommandInterface $command);

    /**
     * Sends an event via the transport.
     *
     * @param DomainEventInterface $event
     * @throws \Exception
     */
    //public function sendEvent(DomainEventInterface $event);

    /**
     * Sends a request via the transport.
     *
     * @param RequestInterface $request
     * @return mixed
     * @throws \Exception
     */
    //public function sendRequest(RequestInterface $request);
}