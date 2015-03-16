<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Mixin\Command;
use Gdbots\Pbj\Mixin\DomainEvent;
use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbjx\Domain\Request\RequestHandlingFailedV1;
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
        PbjxEventBroadcaster::broadcast($this->dispatcher, $command, new ValidateCommandEvent($command), PbjxEvents::COMMAND_VALIDATE);
        PbjxEventBroadcaster::broadcast($this->dispatcher, $command, new EnrichCommandEvent($command), PbjxEvents::COMMAND_ENRICH);
        $this->locator->getCommandBus()->send($command);
    }

    /**
     * {@inheritdoc}
     */
    public function publish(DomainEvent $domainEvent)
    {
        PbjxEventBroadcaster::broadcast($this->dispatcher, $domainEvent, new EnrichDomainEventEvent($domainEvent), PbjxEvents::EVENT_ENRICH);
        $this->locator->getEventBus()->publish($domainEvent);
    }

    /**
     * {@inheritdoc}
     */
    public function request(Request $request)
    {
        $deferred = new Deferred();

        try {
            PbjxEventBroadcaster::broadcast($this->dispatcher, $request, new ValidateRequestEvent($request), PbjxEvents::REQUEST_VALIDATE);
            PbjxEventBroadcaster::broadcast($this->dispatcher, $request, new EnrichRequestEvent($request), PbjxEvents::REQUEST_ENRICH);
            $event = new RequestBusEvent($request);
            PbjxEventBroadcaster::broadcast($this->dispatcher, $request, $event, PbjxEvents::REQUEST_BEFORE_HANDLE);
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
        PbjxEventBroadcaster::broadcast($this->dispatcher, $request, $event, PbjxEvents::REQUEST_AFTER_HANDLE);

        return $deferred->promise();
    }
}
