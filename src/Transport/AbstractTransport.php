<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\StringUtils;
use Gdbots\Pbjx\Event\TransportEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Mixin\Response\Response;
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

            // fallback handling if the transport is down
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
        $code = $exception->getCode() > 0 ? $exception->getCode() : Code::UNKNOWN;

        $response = RequestFailedResponseV1::create()
            ->set('ctx_request_ref', $request->generateMessageRef())
            ->set('ctx_request', $request)
            ->set('error_code', $code)
            ->set('error_name', ClassUtils::getShortName($exception))
            ->set('error_message', substr($exception->getMessage(), 0, 2048))
            ->set('stack_trace', $exception->getTraceAsString());

        if ($exception->getPrevious()) {
            $response->set('prev_error_message', substr($exception->getPrevious()->getMessage(), 0, 2048));
        }

        if ($request->has('ctx_correlator_ref')) {
            $response->set('ctx_correlator_ref', $request->get('ctx_correlator_ref'));
        }

        return $response;
    }
}
