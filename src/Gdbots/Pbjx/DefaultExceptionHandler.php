<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageCurie;
use Gdbots\Pbjx\Event\CommandBusExceptionEvent;
use Gdbots\Pbjx\Event\PbjxEvent;
use Psr\Log\LoggerInterface;

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
     * @param Dispatcher $dispatcher
     * @param ServiceLocator $locator
     * @param LoggerInterface $logger
     */
    public function __construct(Dispatcher $dispatcher, ServiceLocator $locator, LoggerInterface $logger)
    {
        $this->dispatcher = $dispatcher;
        $this->locator = $locator;
        $this->logger = $logger;
        $this->pbjx = $this->locator->getPbjx();
    }

    /**
     * {@inheritdoc}
     */
    public function onCommandBusException(CommandBusExceptionEvent $event)
    {
        $command = $event->getCommand();
        $this->logException($command, $event->getException());
        $this->dispatchEvents(PbjxEvents::COMMAND_HANDLE_EXCEPTION, $event, $command::schema()->getId()->getCurie());
    }

    /**
     * @param Message $message
     * @param \Exception $exception
     */
    protected function logException(Message $message, \Exception $exception)
    {
        if ($message instanceof Command) {
            $this->logger->error(
                sprintf(
                    'Command with id [%s] could not be handled.  Reason: %s' . PHP_EOL .
                    'Payload:' . PHP_EOL . '%s',
                    $message->getCommandId(),
                    $exception->getMessage(),
                    $message
                )
            );
            return;
        }
    }

    /**
     * @param string $eventName
     * @param PbjxEvent $event
     * @param MessageCurie $curie
     */
    protected function dispatchEvents($eventName, PbjxEvent $event, MessageCurie $curie)
    {
        $this->dispatcher->dispatch($eventName, $event);
        $this->dispatcher->dispatch($curie->toString() . '.exception', $event);
    }
}
