<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\ClassUtil;
use Gdbots\Pbj\Util\StringUtil;
use Gdbots\Pbjx\Event\TransportEvent;
use Gdbots\Pbjx\Event\TransportExceptionEvent;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Request\RequestFailedResponseV1;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class AbstractTransport implements Transport
{
    protected EventDispatcherInterface $dispatcher;
    protected ServiceLocator $locator;
    protected string $transportName;

    public function __construct(ServiceLocator $locator)
    {
        $this->locator = $locator;
        $this->dispatcher = $this->locator->getDispatcher();
        $this->transportName = StringUtil::toSnakeFromCamel(
            str_replace('Transport', '', ClassUtil::getShortName($this))
        );
    }

    public function sendCommand(Message $command): void
    {
        $transportEvent = new TransportEvent($this->transportName, $command);
        $this->dispatcher->dispatch($transportEvent, PbjxEvents::TRANSPORT_BEFORE_SEND);

        try {
            $this->doSendCommand($command);
        } catch (\Throwable $e) {
            $this->locator->getExceptionHandler()->onTransportException(
                new TransportExceptionEvent($this->transportName, $command, $e)
            );
            throw $e;
        }

        $this->dispatcher->dispatch($transportEvent, PbjxEvents::TRANSPORT_AFTER_SEND);
    }

    /**
     * Override in the transport to handle the actual send.
     *
     * @param Message $command
     *
     * @throws \Throwable
     */
    protected function doSendCommand(Message $command): void
    {
        $this->locator->getCommandBus()->receiveCommand($command);
    }

    public function sendEvent(Message $event): void
    {
        $transportEvent = new TransportEvent($this->transportName, $event);
        $this->dispatcher->dispatch($transportEvent, PbjxEvents::TRANSPORT_BEFORE_SEND);

        try {
            $this->doSendEvent($event);
        } catch (\Throwable $e) {
            $this->locator->getExceptionHandler()->onTransportException(
                new TransportExceptionEvent($this->transportName, $event, $e)
            );
            throw $e;
        }

        $this->dispatcher->dispatch($transportEvent, PbjxEvents::TRANSPORT_AFTER_SEND);
    }

    /**
     * Override in the transport to handle the actual send.
     *
     * @param Message $event
     *
     * @throws \Throwable
     */
    protected function doSendEvent(Message $event): void
    {
        $this->locator->getEventBus()->receiveEvent($event);
    }

    public function sendRequest(Message $request): Message
    {
        $transportEvent = new TransportEvent($this->transportName, $request);
        $this->dispatcher->dispatch($transportEvent, PbjxEvents::TRANSPORT_BEFORE_SEND);

        try {
            $response = $this->doSendRequest($request);
        } catch (\Throwable $e) {
            $this->locator->getExceptionHandler()->onTransportException(
                new TransportExceptionEvent($this->transportName, $request, $e)
            );

            // fallback handling if the transport is down
            if ('in_memory' !== $this->transportName) {
                try {
                    $response = $this->locator->getRequestBus()->receiveRequest($request);
                } catch (\Throwable $e) {
                    $response = $this->createResponseForFailedRequest($request, $e);
                }
            } else {
                $response = $this->createResponseForFailedRequest($request, $e);
            }
        }

        $transportEvent = new TransportEvent($this->transportName, $response);
        $this->dispatcher->dispatch($transportEvent, PbjxEvents::TRANSPORT_AFTER_SEND);
        return $response;
    }

    /**
     * Override in the transport to handle the actual send.
     *
     * @param Message $request
     *
     * @return Message
     *
     * @throws \Throwable
     */
    protected function doSendRequest(Message $request): Message
    {
        return $this->locator->getRequestBus()->receiveRequest($request);
    }

    protected function createResponseForFailedRequest(Message $request, \Throwable $exception): Message
    {
        $code = $exception->getCode() > 0 ? $exception->getCode() : Code::UNKNOWN;

        $response = RequestFailedResponseV1::create()
            ->set(RequestFailedResponseV1::CTX_REQUEST_REF_FIELD, $request->generateMessageRef())
            ->set(RequestFailedResponseV1::CTX_REQUEST_FIELD, $request)
            ->set(RequestFailedResponseV1::ERROR_CODE_FIELD, $code)
            ->set(RequestFailedResponseV1::ERROR_NAME_FIELD, ClassUtil::getShortName($exception))
            ->set(RequestFailedResponseV1::ERROR_MESSAGE_FIELD, substr($exception->getMessage(), 0, 2048))
            ->set(RequestFailedResponseV1::STACK_TRACE_FIELD, $exception->getTraceAsString());

        if ($exception->getPrevious()) {
            $response->set(
                RequestFailedResponseV1::PREV_ERROR_MESSAGE_FIELD,
                substr($exception->getPrevious()->getMessage(), 0, 2048)
            );
        }

        if ($request->has(RequestFailedResponseV1::CTX_CORRELATOR_REF_FIELD)) {
            $response->set(
                RequestFailedResponseV1::CTX_CORRELATOR_REF_FIELD,
                $request->get(RequestFailedResponseV1::CTX_CORRELATOR_REF_FIELD)
            );
        }

        if ($request->has(RequestFailedResponseV1::CTX_TENANT_ID_FIELD)) {
            $response->set(
                RequestFailedResponseV1::CTX_TENANT_ID_FIELD,
                $request->get(RequestFailedResponseV1::CTX_TENANT_ID_FIELD)
            );
        }

        return $response;
    }
}
