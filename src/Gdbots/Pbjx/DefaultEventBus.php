<?php

namespace Gdbots\Pbjx;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbjx\Domain\Event\EventExecutionFailedV1;
use Gdbots\Pbjx\Event\EventBusExceptionEvent;

class DefaultEventBus implements EventBus
{
    /** @var Dispatcher */
    protected $dispatcher;

    /** @var ServiceLocator */
    protected $locator;

    /** @var Transport */
    protected $transport;

    /** @var Pbjx */
    protected $pbjx;

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
        MessageResolver::registerSchema(EventExecutionFailedV1::schema());
    }

    /**
     * {@inheritdoc}
     */
    public function publish(DomainEvent $domainEvent)
    {
        $this->transport->sendEvent($domainEvent->freeze());
    }

    /**
     * {@inheritdoc}
     */
    public function receiveEvent(DomainEvent $domainEvent)
    {
        $this->doPublish($domainEvent->freeze());
    }

    /**
     * Publishes the event to all subscribers using the dispatcher, which processes
     * events in memory.  If any events throw an exception an EventFailed event
     * will be published.
     *
     * @param DomainEvent $domainEvent
     */
    protected function doPublish(DomainEvent $domainEvent)
    {
        $schemaId = $domainEvent::schema()->getId();
        $curie = $schemaId->getCurie();

        $vendor = $curie->getVendor();
        $package = $curie->getPackage();
        $category = $curie->getCategory();

        $this->doDispatch($schemaId->getResolverKey(), $domainEvent);
        $this->doDispatch($curie->toString(), $domainEvent);
        $this->doDispatch(sprintf('%s:%s:%s:*', $vendor, $package, $category), $domainEvent);
        $this->doDispatch(sprintf('%s:%s:*', $vendor, $package), $domainEvent);
        $this->doDispatch(sprintf('%s:*', $vendor), $domainEvent);
    }

    /**
     * todo: need to decouple this dispatch/event subscribing from symfony since our process doesn't
     * publish events with an Event object and you cannot stop propagation.
     *
     * this is left for now with the expectation that we won't subscribe to events and expect to
     * be called like a symfony event listener.
     *
     * @param string $eventName
     * @param DomainEvent $domainEvent
     */
    final protected function doDispatch($eventName, DomainEvent $domainEvent)
    {
        $listeners = $this->dispatcher->getListeners($eventName);
        foreach ($listeners as $listener) {
            try {
                call_user_func($listener, $domainEvent, $this->pbjx);
            } catch (\Exception $e) {
                if ($domainEvent instanceof EventExecutionFailedV1) {
                    $this->locator->getExceptionHandler()->onEventBusException(
                        new EventBusExceptionEvent($domainEvent, $e)
                    );
                    return;
                }

                $failedEvent = EventExecutionFailedV1::create()
                    ->setFailedEvent($domainEvent)
                    ->setReason(ClassUtils::getShortName(get_class($e)) . '::' . $e->getMessage());

                // running in process for now
                $this->receiveEvent($failedEvent);
            }
        }
    }
}
