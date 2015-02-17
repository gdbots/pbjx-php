<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Event\CommandBusExceptionEvent;

interface ExceptionHandler
{
    /**
     * @param CommandBusExceptionEvent $event
     */
    public function onCommandBusException(CommandBusExceptionEvent $event);
}
