<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Util\ClassUtil;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LogAndDispatchExceptionHandler implements ExceptionHandler
{
    protected EventDispatcherInterface $dispatcher;
    protected ServiceLocator $locator;
    protected LoggerInterface $logger;
    protected Pbjx $pbjx;

    public function __construct(ServiceLocator $locator, ?LoggerInterface $logger = null)
    {
        $this->locator = $locator;
        $this->logger = $logger ?: new NullLogger();
        $this->dispatcher = $this->locator->getDispatcher();
        $this->pbjx = $this->locator->getPbjx();
    }

    public function onTriggerException(PbjxEvent $event, string $eventName, \Throwable $exception): void
    {
        $message = sprintf(
            '%s::Message [{pbj_schema}] threw an exception during [%s] trigger.',
            ClassUtil::getShortName($exception), $eventName
        );

        $this->logger->emergency($message, [
            'exception'  => $exception,
            'pbj_schema' => $event->getMessage()->schema()->getId()->toString(),
            'pbj'        => $event->getMessage()->toArray(),
            'trigger'    => $eventName,
        ]);
    }

    public function onCommandBusException(BusExceptionEvent $event): void
    {
        $this->logBusException($event);
        $this->dispatcher->dispatch($event, PbjxEvents::COMMAND_BUS_EXCEPTION);
    }

    public function onEventBusException(BusExceptionEvent $event): void
    {
        $this->logBusException($event);
        $this->dispatcher->dispatch($event, PbjxEvents::EVENT_BUS_EXCEPTION);
    }

    public function onRequestBusException(BusExceptionEvent $event): void
    {
        // because we throw the exception in Pbjx::request
        // we don't need to log it, something up the chain will.
        /*
        $this->logBusException($event, LogLevel::ERROR);
        $this->dispatcher->dispatch($event, PbjxEvents::REQUEST_BUS_EXCEPTION);
        */
    }

    public function onTransportException(TransportExceptionEvent $event): void
    {
        $message = sprintf(
            '%s::Message [{pbj_schema}] could not be sent by [%s] transport.',
            ClassUtil::getShortName($event->getException()),
            $event->getTransportName()
        );

        $this->logger->emergency($message, [
            'exception'  => $event->getException(),
            'pbj_schema' => $event->getMessage()->schema()->getId()->toString(),
            'pbj'        => $event->getMessage()->toArray(),
            'transport'  => $event->getTransportName(),
        ]);

        $this->dispatcher->dispatch($event, PbjxEvents::TRANSPORT_SEND_EXCEPTION);
    }

    private function logBusException(BusExceptionEvent $event, string $level = LogLevel::CRITICAL): void
    {
        $pbjMessage = $event->getMessage();
        $exceptionShortName = ClassUtil::getShortName($event->getException());

        $message = sprintf('%s [{pbj_schema}] could not be handled.', $exceptionShortName);

        $this->logger->log($level, $message, [
            'exception'  => $event->getException(),
            'pbj_schema' => $pbjMessage::schema()->getId()->toString(),
            'pbj'        => $pbjMessage->toArray(),
        ]);
    }
}
