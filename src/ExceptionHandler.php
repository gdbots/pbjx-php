<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;

interface ExceptionHandler
{
    /**
     * @param BusExceptionEvent $event
     */
    public function onCommandBusException(BusExceptionEvent $event): void;

    /**
     * @param BusExceptionEvent $event
     */
    public function onEventBusException(BusExceptionEvent $event): void;

    /**
     * @param BusExceptionEvent $event
     */
    public function onRequestBusException(BusExceptionEvent $event): void;

    /**
     * @param TransportExceptionEvent $event
     */
    public function onTransportException(TransportExceptionEvent $event): void;
}
