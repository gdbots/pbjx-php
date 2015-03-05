<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Event\CommandBusExceptionEvent;
use Gdbots\Pbjx\Event\EventBusExceptionEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;

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
     */
    public function onTransportException(TransportExceptionEvent $event);
}
