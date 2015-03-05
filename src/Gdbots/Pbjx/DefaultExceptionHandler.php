<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Event\CommandBusExceptionEvent;
use Gdbots\Pbjx\Event\EventBusExceptionEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DefaultExceptionHandler implements ExceptionHandler
{
    /** @var Dispatcher */
    protected $dispatcher;

    /** @var ServiceLocator */
    protected $locator;

    /** @var LoggerInterface */
    protected $logger;

    /** @var Pbjx */
    protected $pbjx;

    /**
     * @param ServiceLocator $locator
     * @param LoggerInterface $logger
     */
    public function __construct(ServiceLocator $locator, LoggerInterface $logger = null)
    {
        $this->locator = $locator;
        $this->logger = $logger ?: new NullLogger();
        $this->dispatcher = $this->locator->getDispatcher();
        $this->pbjx = $this->locator->getPbjx();
    }

    /**
     * {@inheritdoc}
     */
    public function onCommandBusException(CommandBusExceptionEvent $event)
    {
        $command = $event->getCommand();
        $this->logger->critical(
            sprintf(
                'Command with id [%s] could not be handled.  Reason: %s' . PHP_EOL .
                'Payload:' . PHP_EOL . '%s',
                $command->getCommandId(),
                $event->getException()->getMessage(),
                $command
            )
        );
        $this->dispatcher->dispatch(PbjxEvents::COMMAND_HANDLE_EXCEPTION, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function onEventBusException(EventBusExceptionEvent $event)
    {
        $domainEvent = $event->getDomainEvent();
        $this->logger->critical(
            sprintf(
                'Domain event with id [%s] could not be handled.  Reason: %s' . PHP_EOL .
                'Payload:' . PHP_EOL . '%s',
                $domainEvent->getEventId(),
                $event->getException()->getMessage(),
                $domainEvent
            )
        );
        $this->dispatcher->dispatch(PbjxEvents::EVENT_EXCEPTION, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function onTransportException(TransportExceptionEvent $event)
    {
        $message = $event->getMessage();
        $schemaId = $message::schema()->getId();
        $this->logger->emergency(
            sprintf(
                'Message [%s] could not be sent by [%s] transport.  Reason: %s' . PHP_EOL .
                'Payload:' . PHP_EOL . '%s',
                $schemaId->toString(),
                $event->getTransportName(),
                $event->getException()->getMessage(),
                $message
            )
        );
        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_SEND_EXCEPTION, $event);
    }
}
