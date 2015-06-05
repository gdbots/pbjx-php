<?php

namespace Gdbots\Pbjx;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbj\DomainEvent;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Event\EventExecutionFailed;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DefaultEventBus implements EventBus
{
    /** @var EventDispatcherInterface */
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
        MessageResolver::registerSchema(EventExecutionFailed::schema());
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
     * events in memory.  If any events throw an exception an EventExecutionFailed
     * event will be published.
     *
     * @param DomainEvent $domainEvent
     */
    protected function doPublish(DomainEvent $domainEvent)
    {
        $schema = $domainEvent::schema();
        $curie = $schema->getCurie();

        $vendor = $curie->getVendor();
        $package = $curie->getPackage();
        $category = $curie->getCategory();

        $this->doDispatch($schema->getCurieWithMajorRev(), $domainEvent);
        $this->doDispatch($curie->toString(), $domainEvent);

        foreach ($schema->getMixinIds() as $mixinId) {
            $this->doDispatch($mixinId, $domainEvent);
        }

        $this->doDispatch(sprintf('%s:%s:%s:*', $vendor, $package, $category), $domainEvent);
        $this->doDispatch(sprintf('%s:%s:*', $vendor, $package), $domainEvent);
        $this->doDispatch(sprintf('%s:*', $vendor), $domainEvent);
    }

    /**
     * todo: need to decouple this dispatch/event subscribing from symfony since our process doesn't
     * publish events with an Event object and you cannot stop propagation.
     *
     * this is left for now with the expectation that we won't subscribe to these events and
     * expect to be called like a symfony event listener.
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
                if ($domainEvent instanceof EventExecutionFailed) {
                    $this->locator->getExceptionHandler()->onEventBusException(
                        new BusExceptionEvent($domainEvent, $e)
                    );
                    return;
                }

                $failedEvent = EventExecutionFailed::create()
                    ->setFailedEvent($domainEvent)
                    ->setReason(ClassUtils::getShortName($e) . '::' . $e->getMessage());

                if ($domainEvent->hasCorrelator()) {
                    $failedEvent->setCorrelator($domainEvent->getCorrelator());
                }

                // running in process for now
                $this->receiveEvent($failedEvent);
            }
        }
    }
}
