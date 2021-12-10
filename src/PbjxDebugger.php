<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\TransportEvent;
use Psr\Log\LoggerInterface;

final class PbjxDebugger implements EventSubscriber
{
    private LoggerInterface $logger;

    public static function getSubscribedEvents(): array
    {
        return [
            '*'                               => 'onEvent',
            PbjxEvents::TRANSPORT_BEFORE_SEND => 'onTransportEvent',
            PbjxEvents::TRANSPORT_AFTER_SEND  => 'onTransportEvent',
        ];
    }

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onEvent(Message $event, Pbjx $pbjx): void
    {
        $this->logger->debug('PbjxDebugger event [{pbj_schema}] published.', [
            'pbj_schema' => $event::schema()->getId()->toString(),
            'pbj'        => $event->toArray(),
        ]);
    }

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
}
