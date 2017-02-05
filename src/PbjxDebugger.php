<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Event\TransportEvent;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Psr\Log\LoggerInterface;

final class PbjxDebugger implements EventSubscriber
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param Event $event
     * @param Pbjx  $pbjx
     */
    public function onEvent(Event $event, Pbjx $pbjx): void
    {
        $this->logger->debug('PbjxDebugger event [{pbj_schema}] published.', [
            'pbj_schema' => $event::schema()->getId()->toString(),
            'pbj'        => $event->toArray(),
        ]);
    }

    /**
     * @param TransportEvent $event
     * @param string         $eventName
     */
    public function onTransportEvent(TransportEvent $event, ?string $eventName = null): void
    {
        $message = $event->getMessage();
        $this->logger->debug('PbjxDebugger [{event_name}] using [{transport}] transport with [{pbj_schema}].', [
            'transport'  => $event->getTransportName(),
            'event_name' => $eventName,
            'pbj_schema' => $message::schema()->getId()->toString(),
            'pbj'        => $message->toArray(),
        ]);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            '*'                               => 'onEvent',
            PbjxEvents::TRANSPORT_BEFORE_SEND => 'onTransportEvent',
            PbjxEvents::TRANSPORT_AFTER_SEND  => 'onTransportEvent',
        ];
    }
}
