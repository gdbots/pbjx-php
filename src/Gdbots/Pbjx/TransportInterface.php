<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\CommandBus\CommandInterface;
use Gdbots\Pbjx\EventBus\DomainEventInterface;
use Gdbots\Pbjx\RequestBus\RequestInterface;

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