<?php

namespace Gdbots\Pbjx;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\StringUtils;
use Gdbots\Pbjx\Exception\InvalidHandler;
use Gdbots\Pbjx\Exception\UnexpectedValueException;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Request\Request;
use Gdbots\Schemas\Pbjx\Request\Response;
use Gdbots\Schemas\Pbjx\Request\RequestFailedResponseV1;

class DefaultRequestBus implements RequestBus
{
    /** @var ServiceLocator */
    protected $locator;

    /** @var Transport */
    protected $transport;

    /** @var Pbjx */
    protected $pbjx;

    /** @var RequestHandler[] */
    private $handlers = [];

    /**
     * @param ServiceLocator $locator
     * @param Transport $transport
     */
    public function __construct(ServiceLocator $locator, Transport $transport)
    {
        $this->locator = $locator;
        $this->transport = $transport;
        $this->pbjx = $this->locator->getPbjx();
    }

    /**
     * {@inheritdoc}
     */
    public function request(Request $request)
    {
        return $this->transport->sendRequest($request->freeze());
    }

    /**
     * {@inheritdoc}
     */
    public function receiveRequest(Request $request)
    {
        return $this->handleRequest($request->freeze());
    }

    /**
     * Invokes the handler that services the given request.  If an exception occurs
     * it will be caught and a RequestFailedResponse will be created with the reason.
     *
     * @param Request $request
     * @return Response
     */
    final protected function handleRequest(Request $request)
    {
        $curie = $request::schema()->getCurie();
        $curieStr = $curie->toString();

        if (isset($this->handlers[$curieStr])) {
            $handler = $this->handlers[$curieStr];
        } else {
            try {
                $handler = $this->locator->getRequestHandler($curie);
                if (!$handler instanceof RequestHandler) {
                    throw new InvalidHandler(
                        sprintf('The class "%s" must implement RequestHandler.', get_class($handler))
                    );
                }
            } catch (\Exception $e) {
                return $this->createResponseForFailedRequest($request, $e);
            }

            $this->handlers[$curieStr] = $handler;
        }

        try {
            $response = $handler->handleRequest($request, $this->pbjx);
            if (!$response instanceof Response) {
                throw new UnexpectedValueException(
                    sprintf(
                        'The handler "%s" returned "%s" but a Response object was expected.',
                        get_class($handler),
                        StringUtils::varToString($response)
                    )
                );
            }

            $response->set('request_ref', $request->generateMessageRef());
            if ($request->has('correlator')) {
                $response->set('correlator', $request->get('correlator'));
            }

            return $response;
        } catch (\Exception $e) {
            return $this->createResponseForFailedRequest($request, $e);
        }
    }

    /**
     * @param Request $request
     * @param \Exception $exception
     * @return Response
     */
    private function createResponseForFailedRequest(Request $request, \Exception $exception)
    {
        $code = $exception->getCode() > 0 ? $exception->getCode() : Code::UNKNOWN;

        $response = RequestFailedResponseV1::create()
            ->set('request_ref', $request->generateMessageRef())
            ->set('request', $request)
            ->set('error_code', $code)
            ->set('error_name', ClassUtils::getShortName($exception))
            ->set('error_message', $exception->getMessage());

        if ($request->has('correlator')) {
            $response->set('correlator', $request->get('correlator'));
        }

        return $response;
    }
}
