<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Event\CommandBusExceptionEvent;
use Gdbots\Pbjx\Event\EventBusExceptionEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface ExceptionHandler
{
    /**
     * @param CommandBusExceptionEvent $event
     */
    public function onCommandBusException(CommandBusExceptionEvent $event);

    /**
     * @param EventBusExceptionEvent $event
     */
    public function onEventBusException(EventBusExceptionEvent $event);

    /**
     * @param TransportExceptionEvent $event
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function onTransportException(TransportExceptionEvent $event);
}
