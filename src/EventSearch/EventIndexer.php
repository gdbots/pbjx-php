<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventSearch;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\EventSubscriber;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
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

        if ($event->has(EventV1Mixin::CTX_TENANT_ID_FIELD)) {
            $context['tenant_id'] = $event->get(EventV1Mixin::CTX_TENANT_ID_FIELD);
        }

        $pbjx->getEventSearch()->indexEvents([$event], $context);
    }
}
