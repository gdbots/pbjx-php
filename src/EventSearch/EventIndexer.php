<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventSearch;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Mixin\Indexed\IndexedV1Mixin;

final class EventIndexer implements EventSubscriber
{
    public static function getSubscribedEvents()
    {
        return [
            IndexedV1Mixin::SCHEMA_CURIE => 'onIndexed',
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
