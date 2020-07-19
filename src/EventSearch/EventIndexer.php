<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventSearch;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\Pbjx;

final class EventIndexer implements EventSubscriber
{
    public static function getSubscribedEvents()
    {
        return [
            'gdbots:pbjx:mixin:indexed' => 'onIndexed',
        ];
    }

    public function onIndexed(Message $event, Pbjx $pbjx): void
    {
        if ($event->isReplay()) {
            return;
        }

        $context = ['causator' => $event];
        $pbjx->getEventSearch()->indexEvents([$event], $context);
    }
}
