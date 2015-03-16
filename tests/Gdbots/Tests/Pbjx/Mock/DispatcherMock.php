<?php

namespace Gdbots\Tests\Pbjx\Mock;

use Gdbots\Pbjx\Dispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;

class DispatcherMock extends EventDispatcher implements Dispatcher
{
    /**
     * {@inheritdoc}
     */
    public function dispatch($eventName, Event $event = null)
    {
        echo $eventName . PHP_EOL;
        return parent::dispatch($eventName, $event);
    }
}
