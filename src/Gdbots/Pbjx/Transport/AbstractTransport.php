<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbjx\Dispatcher;
use Gdbots\Pbjx\Event\TransportEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Pbjx\Transport;

abstract class AbstractTransport implements Transport
{
    /** @var Dispatcher */
    protected $dispatcher;

    /** @var ServiceLocator */
    protected $locator;

    /* @var string */
    protected $transportName;

    /**
     * @param ServiceLocator $locator
     */
    public function __construct(ServiceLocator $locator)
    {
        $this->locator = $locator;
        $this->dispatcher = $this->locator->getDispatcher();
        $this->transportName = strtolower(str_replace('Transport', '', ClassUtils::getShortName($this)));
    }

    /**
     * {@inheritdoc}
     */
    public function sendCommand(Command $command)
    {
        $event = new TransportEvent($this->transportName, $command);
        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_BEFORE_SEND, $event);

        try {
            $this->doSendCommand($command);
        } catch (\Exception $e) {
            $this->locator->getExceptionHandler()->onTransportException(
                new TransportExceptionEvent($this->transportName, $command, $e)
            );
            return;
        }

        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_AFTER_SEND, $event);
    }

    /**
     * Override in the transport to handle the actual send.
     *
     * @param Command $command
     * @throws \Exception
     */
    abstract protected function doSendCommand(Command $command);
}
