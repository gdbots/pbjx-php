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
use React\Promise\LazyPromise;

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
        $that = $this;
        $factory = function () use ($that, $request) {
            $deferred = new Deferred();
            $notifier = new RequestNotifier($deferred);

            $curie = $request::schema()->getId()->getCurie()->toString();

            try {
                $event = new ValidateRequestEvent($request);
                $that->dispatcher->dispatch(PbjxEvents::REQUEST_VALIDATE, $event);
                $that->dispatcher->dispatch($curie . '.validate', $event);

                $event = new EnrichRequestEvent($request);
                $that->dispatcher->dispatch(PbjxEvents::REQUEST_ENRICH, $event);
                $that->dispatcher->dispatch($curie . '.enrich', $event);

                $event = new RequestBusEvent($request);
                $that->dispatcher->dispatch(PbjxEvents::REQUEST_BEFORE_HANDLE, $event);
                $that->dispatcher->dispatch($curie . '.before_handle', $event);
            } catch (\Exception $e) {
                $deferred->reject($e);
                return $deferred->promise();
            }

            if ($event->hasResponse()) {
                $deferred->resolve($event->getResponse());
                return $deferred->promise();
            }

            // todo: handle async issues with notifier and promise not returning until later
            $response = $that->locator->getRequestBus()->request($request, $notifier);
            if ($response instanceof RequestHandlingFailedV1) {
                $deferred->reject(new RequestHandlingFailed($response));
                return $deferred->promise();
            }

            $event->setResponse($response);
            $that->dispatcher->dispatch(PbjxEvents::REQUEST_AFTER_HANDLE, $event);
            $that->dispatcher->dispatch($curie . '.after_handle', $event);

            return $deferred->promise();
        };

        $promise = new LazyPromise($factory);
        return $promise;
    }
}
