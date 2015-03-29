<?php

namespace Gdbots\Pbjx;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Simple dispatcher that is primarily used for tests or
 * very small apps that register all handlers/listeners
 * at boot time and not using a container.
 */
final class SimpleDispatcher extends EventDispatcher implements Dispatcher
{
    /**
     * {@inheritdoc}
     */
    /*
    public function dispatch($eventName, Event $event = null)
    {
        echo $eventName . PHP_EOL;
        return parent::dispatch($eventName, $event);
    }
    */
}
