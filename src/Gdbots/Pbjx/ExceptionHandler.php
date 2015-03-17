<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;

interface ExceptionHandler
{
    /**
     * @param BusExceptionEvent $event
     */
    public function onCommandBusException(BusExceptionEvent $event);

    /**
     * @param BusExceptionEvent $event
     */
    public function onEventBusException(BusExceptionEvent $event);

    /**
     * @param BusExceptionEvent $event
     */
    public function onRequestBusException(BusExceptionEvent $event);

    /**
     * @param TransportExceptionEvent $event
     */
    public function onTransportException(TransportExceptionEvent $event);
}
