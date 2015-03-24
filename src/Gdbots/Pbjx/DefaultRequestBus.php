<?php

namespace Gdbots\Pbjx;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\StringUtils;
use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbj\Mixin\Response;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbjx\Domain\Request\RequestHandlingFailedV1;
use Gdbots\Pbjx\Exception\InvalidHandler;
use Gdbots\Pbjx\Exception\UnexpectedValueException;

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
        MessageResolver::registerSchema(RequestHandlingFailedV1::schema());
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
     * it will be caught and a RequestHandlingFailedV1 response will be created
     * with the reason.
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
                return RequestHandlingFailedV1::create()
                    ->setRequestId($request->getRequestId())
                    ->setFailedRequest($request)
                    ->setReason(ClassUtils::getShortName($e) . '::' . $e->getMessage());
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
            return $response->setRequestId($request->getRequestId());
        } catch (\Exception $e) {
            return RequestHandlingFailedV1::create()
                ->setRequestId($request->getRequestId())
                ->setFailedRequest($request)
                ->setReason(ClassUtils::getShortName($e) . '::' . $e->getMessage());
        }
    }
}