<?php

namespace Gdbots\Pbjx\EventSearch;

use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Indexed\Indexed;

class EventIndexer implements EventSubscriber
{
    /**
     * @param Indexed $event
     * @param Pbjx $pbjx
     */
    public function onIndexed(Event $event, Pbjx $pbjx)
    {
        if ($event->isReplay()) {
            return;
        }

        $pbjx->getEventSearch()->index([$event]);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'gdbots:pbjx:mixin:indexed' => 'onIndexed',
            'gdbots:pbjx:mixin:event' => 'onIndexed',
        ];
    }
}
