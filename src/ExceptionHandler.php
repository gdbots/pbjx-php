<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;

interface ExceptionHandler
{
    public function onTriggerException(PbjxEvent $event, string $eventName, \Throwable $exception): void;

    public function onCommandBusException(BusExceptionEvent $event): void;

    public function onEventBusException(BusExceptionEvent $event): void;

    public function onRequestBusException(BusExceptionEvent $event): void;

    public function onTransportException(TransportExceptionEvent $event): void;
}
