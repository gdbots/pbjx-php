<?php

namespace Gdbots\Pbjx\Transport;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Gdbots\Pbjx\CommandBus\CommandInterface;
use Gdbots\Pbjx\LifecycleEvent\Transport\TransportEvent;
use Gdbots\Pbjx\LifecycleEvent\Transport\TransportEvents;
use Gdbots\Pbjx\LifecycleEvent\Transport\TransportExceptionEvent;
use Gdbots\Pbjx\ServiceLocatorInterface;
use Gdbots\Pbjx\TransportInterface;

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
