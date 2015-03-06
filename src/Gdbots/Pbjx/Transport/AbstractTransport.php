<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\StringUtils;
use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbjx\Dispatcher;
use Gdbots\Pbjx\Domain\Response\RequestHandlingFailedV1;
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
        $this->transportName = StringUtils::toSlugFromCamel(
            str_replace('Transport', '', ClassUtils::getShortName($this))
        );
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
            throw $e;
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

    /**
     * {@inheritdoc}
     */
    public function sendEvent(DomainEvent $domainEvent)
    {
        $event = new TransportEvent($this->transportName, $domainEvent);
        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_BEFORE_SEND, $event);

        try {
            $this->doSendEvent($domainEvent);
        } catch (\Exception $e) {
            $this->locator->getExceptionHandler()->onTransportException(
                new TransportExceptionEvent($this->transportName, $domainEvent, $e)
            );
            throw $e;
        }

        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_AFTER_SEND, $event);
    }

    /**
     * Override in the transport to handle the actual send.
     *
     * @param DomainEvent $domainEvent
     * @throws \Exception
     */
    abstract protected function doSendEvent(DomainEvent $domainEvent);

    /**
     * {@inheritdoc}
     */
    public function sendRequest(Request $request)
    {
        $event = new TransportEvent($this->transportName, $request);
        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_BEFORE_SEND, $event);

        try {
            $response = $this->doSendRequest($request);
        } catch (\Exception $e) {
            /*
             * fallback handling if the transport is down
             */
            if ('in-memory' !== $this->transportName) {
                try {
                    $response = $this->locator->getRequestBus()->receiveRequest($request);
                } catch (\Exception $e) {
                    $response = RequestHandlingFailedV1::create()
                        ->setRequestId($request->getRequestId())
                        ->setFailedRequest($request)
                        ->setReason(ClassUtils::getShortName($e) . '::' . $e->getMessage());
                }
            } else {
                $this->locator->getExceptionHandler()->onTransportException(
                    new TransportExceptionEvent($this->transportName, $request, $e)
                );
                $response = RequestHandlingFailedV1::create()
                    ->setRequestId($request->getRequestId())
                    ->setFailedRequest($request)
                    ->setReason(ClassUtils::getShortName($e) . '::' . $e->getMessage());
            }
        }

        $event = new TransportEvent($this->transportName, $response);
        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_AFTER_SEND, $event);
        return $response;
    }

    /**
     * Override in the transport to handle the actual send.
     *
     * @param Request $request
     * @throws \Exception
     */
    abstract protected function doSendRequest(Request $request);
}
