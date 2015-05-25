<?php

namespace Gdbots\Pbjx;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DefaultExceptionHandler implements ExceptionHandler
{
    /** @var EventDispatcherInterface */
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
    public function onCommandBusException(BusExceptionEvent $event)
    {
        $this->logger->critical(
            sprintf(
                'Command could not be handled.  %s::%s' . PHP_EOL . 'Payload:' . PHP_EOL . '%s',
                ClassUtils::getShortName($event->getException()),
                $event->getException()->getMessage(),
                $event->getMessage()
            )
        );
        $this->dispatcher->dispatch(PbjxEvents::COMMAND_BUS_EXCEPTION, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function onEventBusException(BusExceptionEvent $event)
    {
        $this->logger->critical(
            sprintf(
                'Domain event could not be handled.  %s::%s' . PHP_EOL . 'Payload:' . PHP_EOL . '%s',
                ClassUtils::getShortName($event->getException()),
                $event->getException()->getMessage(),
                $event->getMessage()
            )
        );
        $this->dispatcher->dispatch(PbjxEvents::EVENT_BUS_EXCEPTION, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function onRequestBusException(BusExceptionEvent $event)
    {
        $this->logger->error(
            sprintf(
                'Request handling failed.  %s::%s' . PHP_EOL . 'Payload:' . PHP_EOL . '%s',
                ClassUtils::getShortName($event->getException()),
                $event->getException()->getMessage(),
                $event->getMessage()
            )
        );
        $this->dispatcher->dispatch(PbjxEvents::REQUEST_BUS_EXCEPTION, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function onTransportException(TransportExceptionEvent $event)
    {
        $this->logger->emergency(
            sprintf(
                'Message could not be sent by [%s] transport.  %s:%s' . PHP_EOL . 'Payload:' . PHP_EOL . '%s',
                ClassUtils::getShortName($event->getException()),
                $event->getTransportName(),
                $event->getException()->getMessage(),
                $event->getMessage()
            )
        );
        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_SEND_EXCEPTION, $event);
    }
}
