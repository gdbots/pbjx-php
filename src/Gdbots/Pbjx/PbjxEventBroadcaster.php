<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;

final class PbjxEventBroadcaster
{
    /**
     * Dispatches application events (which are handled in memory/in process) for the
     * primary event and then one for the curie + suffix and for each mixin + suffix.
     *
     * Suffix is the last ".blah" part of the eventName.
     *
     * This method simply saves some code duplication, nothing fancy going on here.
     *
     * @param Dispatcher $dispatcher
     * @param Message $message
     * @param PbjxEvent $event
     * @param string $eventName
     */
    public static function broadcast(Dispatcher $dispatcher, Message $message, PbjxEvent $event, $eventName)
    {
        $dispatcher->dispatch($eventName, $event);
        $eventSuffix = substr($eventName, strrpos($eventName, '.'));

        $schema = $message::schema();
        $curie = $schema->getId()->getCurie()->toString();

        $dispatcher->dispatch($curie . $eventSuffix, $event);

        foreach ($schema->getMixinIds() as $mixinId) {
            $dispatcher->dispatch($mixinId . $eventSuffix, $event);
        }
    }
}
