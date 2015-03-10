<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbjx\Domain\Response\RequestHandlingFailedV1;
use Gdbots\Pbjx\Event\EnrichCommandEvent;
use Gdbots\Pbjx\Event\EnrichDomainEventEvent;
use Gdbots\Pbjx\Event\EnrichRequestEvent;
use Gdbots\Pbjx\Event\RequestBusEvent;
use Gdbots\Pbjx\Event\ValidateCommandEvent;
use Gdbots\Pbjx\Event\ValidateRequestEvent;
use Gdbots\Pbjx\Exception\RequestHandlingFailed;
use React\Promise\Deferred;

class DefaultPbjx implements Pbjx
{
    /** @var Dispatcher */
    protected $dispatcher;

    /** @var ServiceLocator */
    protected $locator;

    /**
     * @param ServiceLocator $locator
     */
    public function __construct(ServiceLocator $locator)
    {
        $this->locator = $locator;
        $this->dispatcher = $this->locator->getDispatcher();
    }

    /**
     * {@inheritdoc}
     */
    public function send(Command $command)
    {
        $curie = $command::schema()->getId()->getCurie()->toString();

        $event = new ValidateCommandEvent($command);
        $this->dispatcher->dispatch(PbjxEvents::COMMAND_VALIDATE, $event);
        $this->dispatcher->dispatch($curie . '.validate', $event);

        $event = new EnrichCommandEvent($command);
        $this->dispatcher->dispatch(PbjxEvents::COMMAND_ENRICH, $event);
        $this->dispatcher->dispatch($curie . '.enrich', $event);

        $this->locator->getCommandBus()->send($command);
    }

    /**
     * {@inheritdoc}
     */
    public function publish(DomainEvent $domainEvent)
    {
        $curie = $domainEvent::schema()->getId()->getCurie()->toString();

        $event = new EnrichDomainEventEvent($domainEvent);
        $this->dispatcher->dispatch(PbjxEvents::EVENT_ENRICH, $event);
        $this->dispatcher->dispatch($curie . '.enrich', $event);

        $this->locator->getEventBus()->publish($domainEvent);
    }

    /**
     * {@inheritdoc}
     */
    public function request(Request $request)
    {
        $curie = $request::schema()->getId()->getCurie()->toString();
        $deferred = new Deferred();

        try {
            $event = new ValidateRequestEvent($request);
            $this->dispatcher->dispatch(PbjxEvents::REQUEST_VALIDATE, $event);
            $this->dispatcher->dispatch($curie . '.validate', $event);

            $event = new EnrichRequestEvent($request);
            $this->dispatcher->dispatch(PbjxEvents::REQUEST_ENRICH, $event);
            $this->dispatcher->dispatch($curie . '.enrich', $event);

            $event = new RequestBusEvent($request);
            $this->dispatcher->dispatch(PbjxEvents::REQUEST_BEFORE_HANDLE, $event);
            $this->dispatcher->dispatch($curie . '.before_handle', $event);
        } catch (\Exception $e) {
            $deferred->reject($e);
            return $deferred->promise();
        }

        if ($event->hasResponse()) {
            $response = $event->getResponse();
            if (!$response->isFrozen()) {
                if ($request->hasCorrelator() && !$response->hasCorrelator()) {
                    $response->setCorrelator($request->getCorrelator());
                }
            }
            $deferred->resolve($response);
            return $deferred->promise();
        }

        $response = $this->locator->getRequestBus()->request($request);
        if (!$response->isFrozen()) {
            if ($request->hasCorrelator() && !$response->hasCorrelator()) {
                $response->setCorrelator($request->getCorrelator());
            }
        }

        if ($response instanceof RequestHandlingFailedV1) {
            $deferred->reject(new RequestHandlingFailed($response));
            return $deferred->promise();
        }

        $deferred->resolve($response);
        $event->setResponse($response);
        $this->dispatcher->dispatch(PbjxEvents::REQUEST_AFTER_HANDLE, $event);
        $this->dispatcher->dispatch($curie . '.after_handle', $event);

        return $deferred->promise();
    }
}
