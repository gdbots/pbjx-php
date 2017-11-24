<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbjx\Transport\Transport;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Mixin\Response\Response;
use Gdbots\Schemas\Pbjx\Request\RequestFailedResponseV1;

final class SimpleRequestBus implements RequestBus
{
    /** @var ServiceLocator */
    private $locator;

    /** @var Transport */
    private $transport;

    /** @var RequestHandler[] */
    private $handlers = [];

    /**
     * @param ServiceLocator $locator
     * @param Transport      $transport
     */
    public function __construct(ServiceLocator $locator, Transport $transport)
    {
        $this->locator = $locator;
        $this->transport = $transport;
    }

    /**
     * {@inheritdoc}
     */
    public function request(Request $request): Response
    {
        return $this->transport->sendRequest($request->freeze());
    }

    /**
     * Invokes the handler that services the given request.  If an exception occurs
     * it will be caught and a RequestFailedResponse will be created with the reason.
     *
     * {@inheritdoc}
     */
    public function receiveRequest(Request $request): Response
    {
        $curie = $request::schema()->getCurie();
        $curieStr = $curie->toString();

        if (isset($this->handlers[$curieStr])) {
            $handler = $this->handlers[$curieStr];
        } else {
            try {
                $handler = $this->locator->getRequestHandler($curie);
            } catch (\Exception $e) {
                return $this->createResponseForFailedRequest($request, $e);
            }

            $this->handlers[$curieStr] = $handler;
        }

        try {
            $request->freeze();
            $response = $handler->handleRequest($request, $this->locator->getPbjx());
            $response->set('ctx_request_ref', $request->generateMessageRef());
            if ($request->has('ctx_correlator_ref')) {
                $response->set('ctx_correlator_ref', $request->get('ctx_correlator_ref'));
            }

            return $response;
        } catch (\Exception $e) {
            return $this->createResponseForFailedRequest($request, $e);
        }
    }

    /**
     * @param Request    $request
     * @param \Exception $exception
     *
     * @return Response
     */
    private function createResponseForFailedRequest(Request $request, \Exception $exception): Response
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
