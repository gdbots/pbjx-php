<?php

namespace Gdbots\Pbjx;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbj\Extension\Response;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbjx\Domain\Response\RequestHandlingFailedV1;
use Gdbots\Pbjx\Exception\InvalidHandler;

class DefaultRequestBus implements RequestBus
{
    /** @var Dispatcher */
    protected $dispatcher;

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
        $this->dispatcher = $this->locator->getDispatcher();
        $this->pbjx = $this->locator->getPbjx();
        MessageResolver::registerSchema(RequestHandlingFailedV1::schema());
    }

    /**
     * {@inheritdoc}
     */
    public function request(Request $request, Notifier $notifier)
    {
        return $this->transport->sendRequest($request->freeze(), $notifier);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveRequest(Request $request, Notifier $notifier)
    {
        return $this->handleRequest($request->freeze(), $notifier);
    }

    /**
     * Invokes the handler that services the given request.  If an exception occurs
     * it will be caught and a RequestHandlingFailedV1 response will be created
     * with the reason.
     *
     * @param Request $request
     * @param Notifier $notifier
     * @return Response
     */
    final protected function handleRequest(Request $request, Notifier $notifier)
    {
        $curie = $request::schema()->getId()->getCurie();
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
                    ->setReason(ClassUtils::getShortName(get_class($e)) . '::' . $e->getMessage());
            }
            $this->handlers[$curieStr] = $handler;
        }

        try {
            return $handler->handleRequest($request, $notifier, $this->pbjx)->setRequestId($request->getRequestId());
        } catch (\Exception $e) {
            return RequestHandlingFailedV1::create()
                ->setRequestId($request->getRequestId())
                ->setFailedRequest($request)
                ->setReason(ClassUtils::getShortName(get_class($e)) . '::' . $e->getMessage());
        }
    }
}
