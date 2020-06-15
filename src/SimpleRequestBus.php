<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\ClassUtil;
use Gdbots\Pbjx\Transport\Transport;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Response\ResponseV1Mixin;
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
            $response->set(ResponseV1Mixin::CTX_REQUEST_REF_FIELD, $request->generateMessageRef());

            if ($request->has(ResponseV1Mixin::CTX_CORRELATOR_REF_FIELD)) {
                $response->set(
                    ResponseV1Mixin::CTX_CORRELATOR_REF_FIELD,
                    $request->get(ResponseV1Mixin::CTX_CORRELATOR_REF_FIELD)
                );
            }

            if ($request->has(ResponseV1Mixin::CTX_TENANT_ID_FIELD)) {
                $response->set(
                    ResponseV1Mixin::CTX_TENANT_ID_FIELD,
                    $request->get(ResponseV1Mixin::CTX_TENANT_ID_FIELD)
                );
            }

            return $response;
        } catch (\Throwable $e) {
            return $this->createResponseForFailedRequest($request, $e);
        }
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
