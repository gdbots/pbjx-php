<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx;

use Gdbots\Schemas\Pbjx\Mixin\Event\Event;

trait EventSubscriberTrait
{
    /**
     * @param Event $event
     * @param Pbjx  $pbjx
     */
    public function onEvent(Event $event, Pbjx $pbjx): void
    {
        $method = 'on' . ucfirst($event::schema()->getHandlerMethodName(false));
        if (is_callable([$this, $method])) {
            $this->$method($event, $pbjx);
        }
    }

    /**
     * @param Event[] $events
     * @param Pbjx    $pbjx
     */
    public function onEvents(array $events, Pbjx $pbjx): void
    {
        foreach ($events as $event) {
            $this->onEvent($event, $pbjx);
        }
    }
}
