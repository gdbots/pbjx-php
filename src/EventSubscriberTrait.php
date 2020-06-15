<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;

trait EventSubscriberTrait
{
    /**
     * @param Message $event
     * @param Pbjx    $pbjx
     */
    public function onEvent(Message $event, Pbjx $pbjx): void
    {
        $method = $event::schema()->getHandlerMethodName(false, 'on');
        if (is_callable([$this, $method])) {
            $this->$method($event, $pbjx);
        }
    }

    /**
     * @param Message[] $events
     * @param Pbjx      $pbjx
     */
    public function onEvents(array $events, Pbjx $pbjx): void
    {
        foreach ($events as $event) {
            $this->onEvent($event, $pbjx);
        }
    }
}
