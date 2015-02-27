<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\DomainEvent;

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
    }

    /**
     * {@inheritdoc}
     */
    public function publish(DomainEvent $domainEvent)
    {
        $this->transport->sendEvent($domainEvent);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveEvent(DomainEvent $domainEvent)
    {
        $this->doPublish($domainEvent);
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

        /*
         * vendor:package:category:message:v1
         * vendor:package:category:message
         * vendor:package:category:*
         * vendor:package:*
         * vendor:*
         */

        $this->doDispatch($schemaId->getResolverKey(), $domainEvent);
        $this->doDispatch($curie->toString(), $domainEvent);
        $this->doDispatch(sprintf('%s:%s:%s:*', $vendor, $package, $category), $domainEvent);
        $this->doDispatch(sprintf('%s:%s:*', $vendor, $package), $domainEvent);
        $this->doDispatch(sprintf('%s:*', $vendor), $domainEvent);
    }

    /**
     * todo: need to handle symfony event signature by wrapping and unwrapping the domain event in custom dispatcher
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
                // todo: publish event failed here
            }
        }
    }
}
