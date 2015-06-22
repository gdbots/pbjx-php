<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\DomainCommand;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface CommandBus
{
    /**
     * Processes a command asynchronously.
     *
     * @param DomainCommand $command
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function send(DomainCommand $command);

    /**
     * Processes a command directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @param DomainCommand $command
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function receiveCommand(DomainCommand $command);
}
