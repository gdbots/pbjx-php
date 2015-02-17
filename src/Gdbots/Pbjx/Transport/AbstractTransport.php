<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbjx\Dispatcher;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Pbjx\Transport;

abstract class AbstractTransport implements Transport
{
    /** @var Dispatcher */
    protected $dispatcher;

    /** @var ServiceLocator */
    protected $locator;

    /**
     * @param Dispatcher $dispatcher
     * @param ServiceLocator $locator
     */
    public function __construct(Dispatcher $dispatcher, ServiceLocator $locator)
    {
        $this->dispatcher = $dispatcher;
        $this->locator = $locator;
    }

    /**
     * {@inheritdoc}
     */
    public function sendCommand(Command $command)
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
     * @param Command $command
     * @throws \Exception
     */
    abstract protected function doSendCommand(Command $command);
}
