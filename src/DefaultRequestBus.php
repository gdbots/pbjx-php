<?php

namespace Gdbots\Pbjx;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\StringUtils;
use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbj\DomainResponse;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbjx\Exception\InvalidHandler;
use Gdbots\Pbjx\Exception\UnexpectedValueException;
use Gdbots\Pbjx\Request\RequestHandlingFailedV1;

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
    public function request(DomainRequest $request)
    {
        return $this->transport->sendRequest($request->freeze());
    }

    /**
     * {@inheritdoc}
     */
    public function receiveRequest(DomainRequest $request)
    {
        return $this->handleRequest($request->freeze());
    }

    /**
     * Invokes the handler that services the given request.  If an exception occurs
     * it will be caught and a RequestHandlingFailedV1 response will be created
     * with the reason.
     *
     * @param DomainRequest $request
     * @return DomainResponse
     */
    final protected function handleRequest(DomainRequest $request)
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
            if (!$response instanceof DomainResponse) {
                throw new UnexpectedValueException(
                    sprintf(
                        'The handler "%s" returned "%s" but a Response object was expected.',
                        get_class($handler),
                        StringUtils::varToString($response)
                    )
                );
            }

            $response->setRequestRef($request->generateMessageRef());
            if ($request->hasCorrelator()) {
                $response->setCorrelator($request->getCorrelator());
            }

            return $response;
        } catch (\Exception $e) {
            return $this->createResponseForFailedRequest($request, $e);
        }
    }

    /**
     * @param DomainRequest $request
     * @param \Exception $exception
     * @return DomainResponse
     */
    private function createResponseForFailedRequest(DomainRequest $request, \Exception $exception)
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
