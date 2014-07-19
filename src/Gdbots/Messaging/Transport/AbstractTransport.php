<?php

namespace Gdbots\Messaging\Transport;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Gdbots\Messaging\CommandBus\CommandInterface;
use Gdbots\Messaging\LifecycleEvent\Transport\TransportEvent;
use Gdbots\Messaging\LifecycleEvent\Transport\TransportEvents;
use Gdbots\Messaging\LifecycleEvent\Transport\TransportExceptionEvent;
use Gdbots\Messaging\ServiceLocatorInterface;
use Gdbots\Messaging\TransportInterface;

abstract class AbstractTransport implements TransportInterface
{
    /* @var EventDispatcherInterface */
    protected $dispatcher;

    /* @var ServiceLocatorInterface */
    protected $locator;

    /**
     * @param EventDispatcherInterface $dispatcher
     * @param ServiceLocatorInterface $locator
     */
    public function __construct(EventDispatcherInterface $dispatcher, ServiceLocatorInterface $locator)
    {
        $this->dispatcher = $dispatcher;
        $this->locator = $locator;
    }

    /**
     * @see TransportInterface::sendCommand
     */
    public function sendCommand(CommandInterface $command)
    {
        $event = new TransportEvent(static::getName(), $command);
        $this->dispatcher->dispatch(TransportEvents::BEFORE_SEND, $event);

        try {
            $this->doSendCommand($command);
        } catch (\Exception $e) {
            $exceptionEvent = new TransportExceptionEvent(static::getName(), $command, $e);
            $this->dispatcher->dispatch(TransportEvents::EXCEPTION, $exceptionEvent);
            throw $e;
        }

        $this->dispatcher->dispatch(TransportEvents::AFTER_SEND, $event);
    }

    /**
     * Override in the transport to handle the actual send.
     *
     * @param CommandInterface $command
     * @throws \Exception
     */
    abstract protected function doSendCommand(CommandInterface $command);
}
