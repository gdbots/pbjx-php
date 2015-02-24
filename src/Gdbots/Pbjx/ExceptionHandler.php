<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Event\CommandBusExceptionEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface ExceptionHandler
{
    /**
     * @param CommandBusExceptionEvent $event
     */
    public function onCommandBusException(CommandBusExceptionEvent $event);

    /**
     * @param TransportExceptionEvent $event
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function onTransportException(TransportExceptionEvent $event);
}
