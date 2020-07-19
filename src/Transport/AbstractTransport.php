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
            ->set('ctx_request_ref', $request->generateMessageRef())
            ->set('ctx_request', $request)
            ->set('error_code', $code)
            ->set('error_name', ClassUtil::getShortName($exception))
            ->set('error_message', substr($exception->getMessage(), 0, 2048))
            ->set('stack_trace', $exception->getTraceAsString());

        if ($exception->getPrevious()) {
            $response->set('prev_error_message', substr($exception->getPrevious()->getMessage(), 0, 2048));
        }

        if ($request->has('ctx_correlator_ref')) {
            $response->set('ctx_correlator_ref', $request->get('ctx_correlator_ref'));
        }

        if ($request->has('ctx_tenant_id')) {
            $response->set('ctx_tenant_id', $request->get('ctx_tenant_id'));
        }

        return $response;
    }
}
