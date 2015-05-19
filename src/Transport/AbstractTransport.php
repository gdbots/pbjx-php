<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\StringUtils;
use Gdbots\Pbj\Command;
use Gdbots\Pbj\DomainEvent;
use Gdbots\Pbj\Request;
use Gdbots\Pbj\Response;
use Gdbots\Pbjx\Event\TransportEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Pbjx\Request\RequestHandlingFailedV1;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Pbjx\Transport;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class AbstractTransport implements Transport
{
    /** @var EventDispatcherInterface */
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
        $this->transportName = StringUtils::toSnakeFromCamel(
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
    protected function doSendCommand(Command $command)
    {
        $this->locator->getCommandBus()->receiveCommand($command);
    }

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
    protected function doSendEvent(DomainEvent $domainEvent)
    {
        $this->locator->getEventBus()->receiveEvent($domainEvent);
    }

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
            $this->locator->getExceptionHandler()->onTransportException(
                new TransportExceptionEvent($this->transportName, $request, $e)
            );

            /*
             * fallback handling if the transport is down
             * todo: review, should we just die here?
             */
            if ('in_memory' !== $this->transportName) {
                try {
                    $response = $this->locator->getRequestBus()->receiveRequest($request);
                } catch (\Exception $e) {
                    $response = $this->createResponseForFailedRequest($request, $e);
                }
            } else {
                $response = $this->createResponseForFailedRequest($request, $e);
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
     * @return Response
     * @throws \Exception
     */
    protected function doSendRequest(Request $request)
    {
        return $this->locator->getRequestBus()->receiveRequest($request);
    }

    /**
     * @param Request $request
     * @param \Exception $exception
     * @return Response
     */
    protected function createResponseForFailedRequest(Request $request, \Exception $exception)
    {
        $response = RequestHandlingFailedV1::create()
            ->setRequestRef($request->generateMessageRef())
            ->setFailedRequest($request)
            ->setReason(ClassUtils::getShortName($exception) . '::' . $exception->getMessage());

        if ($request->hasCorrelator()) {
            $response->setCorrelator($request->getCorrelator());
        }

        return $response;
    }
}
