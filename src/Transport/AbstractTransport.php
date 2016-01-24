<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\StringUtils;
use Gdbots\Pbjx\Event\TransportEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Pbjx\Transport;
use Gdbots\Schemas\Pbj\Command\Command;
use Gdbots\Schemas\Pbj\Event\Event;
use Gdbots\Schemas\Pbj\Request\Request;
use Gdbots\Schemas\Pbj\Request\Response;
use Gdbots\Schemas\Pbjx\Request\RequestFailedResponseV1;
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
        $transportEvent = new TransportEvent($this->transportName, $command);
        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_BEFORE_SEND, $transportEvent);

        try {
            $this->doSendCommand($command);
        } catch (\Exception $e) {
            $this->locator->getExceptionHandler()->onTransportException(
                new TransportExceptionEvent($this->transportName, $command, $e)
            );
            throw $e;
        }

        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_AFTER_SEND, $transportEvent);
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
    public function sendEvent(Event $event)
    {
        $transportEvent = new TransportEvent($this->transportName, $event);
        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_BEFORE_SEND, $transportEvent);

        try {
            $this->doSendEvent($event);
        } catch (\Exception $e) {
            $this->locator->getExceptionHandler()->onTransportException(
                new TransportExceptionEvent($this->transportName, $event, $e)
            );
            throw $e;
        }

        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_AFTER_SEND, $transportEvent);
    }

    /**
     * Override in the transport to handle the actual send.
     *
     * @param Event $event
     * @throws \Exception
     */
    protected function doSendEvent(Event $event)
    {
        $this->locator->getEventBus()->receiveEvent($event);
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(Request $request)
    {
        $transportEvent = new TransportEvent($this->transportName, $request);
        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_BEFORE_SEND, $transportEvent);

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

        $transportEvent = new TransportEvent($this->transportName, $response);
        $this->dispatcher->dispatch(PbjxEvents::TRANSPORT_AFTER_SEND, $transportEvent);
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
        $response = RequestFailedResponseV1::create()
            ->set('request_ref', $request->generateMessageRef())
            ->set('failed_request', $request)
            ->set('reason', ClassUtils::getShortName($exception) . '::' . $exception->getMessage());

        if ($request->has('correlator')) {
            $response->set('correlator', $request->get('correlator'));
        }

        return $response;
    }
}
