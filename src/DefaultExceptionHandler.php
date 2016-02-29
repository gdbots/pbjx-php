<?php

namespace Gdbots\Pbjx;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;
use Gdbots\Schemas\Pbj\Command\Command;
use Gdbots\Schemas\Pbj\Event\Event;
use Gdbots\Schemas\Pbj\Request\Request;
use Gdbots\Schemas\Pbj\Request\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
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
        $this->logBusException($event);
        $this->dispatcher->dispatch(PbjxEvents::COMMAND_BUS_EXCEPTION, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function onEventBusException(BusExceptionEvent $event)
    {
        $this->logBusException($event);
        $this->dispatcher->dispatch(PbjxEvents::EVENT_BUS_EXCEPTION, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function onRequestBusException(BusExceptionEvent $event)
    {
        $this->logBusException($event, LogLevel::ERROR);
        $this->dispatcher->dispatch(PbjxEvents::REQUEST_BUS_EXCEPTION, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function onTransportException(TransportExceptionEvent $event)
    {
        $message = sprintf(
            '%s::Message [{pbj_schema}] could not be sent by [%s] transport.',
            ClassUtils::getShortName($event->getException()),
            $event->getTransportName()
        );

        $this->logger->emergency($message, [
            'exception' => $event->getException(),
            'pbj_schema' => $event->getMessage()->schema()->getId()->toString(),
            'pbj' => $event->getMessage()->toArray(),
            'transport' => $event->getTransportName(),
        ]);

        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_SEND_EXCEPTION, $event);
    }

    /**
     * @param BusExceptionEvent $event
     * @param string $level
     */
    protected function logBusException(BusExceptionEvent $event, $level = LogLevel::CRITICAL)
    {
        $pbjMessage = $event->getMessage();
        $exceptionShortName = ClassUtils::getShortName($event->getException());

        if ($pbjMessage instanceof Command) {
            $type = 'Command';
        } elseif ($pbjMessage instanceof Event) {
            $type = 'Event';
        } elseif ($pbjMessage instanceof Request) {
            $type = 'Request';
        } elseif ($pbjMessage instanceof Response) {
            $type = 'Response';
        } else {
            $type = 'Message';
        }

        $message = sprintf('%s::%s [{pbj_schema}] could not be handled.', $exceptionShortName, $type);

        $this->logger->log($level, $message, [
            'exception' => $event->getException(),
            'pbj_schema' => $pbjMessage::schema()->getId()->toString(),
            'pbj' => $pbjMessage->toArray(),
        ]);
    }
}
