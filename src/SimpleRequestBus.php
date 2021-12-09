<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\ClassUtil;
use Gdbots\Pbjx\Transport\Transport;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Request\RequestFailedResponseV1;

final class SimpleRequestBus implements RequestBus
{
    private ServiceLocator $locator;
    private Transport $transport;

    public function __construct(ServiceLocator $locator, Transport $transport)
    {
        $this->locator = $locator;
        $this->transport = $transport;
    }

    public function request(Message $request): Message
    {
        return $this->transport->sendRequest($request->freeze());
    }

    /**
     * Invokes the handler that services the given request.  If an exception occurs
     * it will be caught and a RequestFailedResponse will be created with the reason.
     *
     * {@inheritdoc}
     */
    public function receiveRequest(Message $request): Message
    {
        try {
            $request->freeze();
            $handler = $this->locator->getRequestHandler($request::schema()->getCurie());
            $response = $handler->handleRequest($request, $this->locator->getPbjx());
            $response->set('ctx_request_ref', $request->generateMessageRef());

            if ($request->has('ctx_correlator_ref')) {
                $response->set('ctx_correlator_ref', $request->get('ctx_correlator_ref'));
            }

            if ($request->has('ctx_tenant_id')) {
                $response->set('ctx_tenant_id', $request->get('ctx_tenant_id'));
            }

            return $response;
        } catch (\Throwable $e) {
            return $this->createResponseForFailedRequest($request, $e);
        }
    }

    protected function createResponseForFailedRequest(Message $request, \Throwable $exception): Message
    {
        $code = $exception->getCode() > 0 ? $exception->getCode() : Code::UNKNOWN->value;

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
