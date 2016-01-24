<?php

namespace Gdbots\Pbjx;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Schemas\Pbj\Event\Event;
use Gdbots\Schemas\Pbjx\Event\EventExecutionFailed;
use Gdbots\Schemas\Pbjx\Event\EventExecutionFailedV1;
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
    }

    /**
     * {@inheritdoc}
     */
    public function publish(Event $event)
    {
        $this->transport->sendEvent($event->freeze());
    }

    /**
     * {@inheritdoc}
     */
    public function receiveEvent(Event $event)
    {
        $this->doPublish($event->freeze());
    }

    /**
     * Publishes the event to all subscribers using the dispatcher, which processes
     * events in memory.  If any events throw an exception an EventExecutionFailed
     * event will be published.
     *
     * @param Event $event
     */
    protected function doPublish(Event $event)
    {
        $schema = $event::schema();
        $curie = $schema->getCurie();

        $vendor = $curie->getVendor();
        $package = $curie->getPackage();
        $category = $curie->getCategory();

        foreach ($schema->getMixinIds() as $mixinId) {
            $this->doDispatch($mixinId, $event);
        }

        $this->doDispatch($schema->getCurieWithMajorRev(), $event);
        $this->doDispatch($curie->toString(), $event);

        $this->doDispatch(sprintf('%s:%s:%s:*', $vendor, $package, $category), $event);
        $this->doDispatch(sprintf('%s:%s:*', $vendor, $package), $event);
        $this->doDispatch(sprintf('%s:*', $vendor), $event);
    }

    /**
     * todo: need to decouple this dispatch/event subscribing from symfony since our process doesn't
     * publish events with an Event object and you cannot stop propagation.
     *
     * this is left for now with the expectation that we won't subscribe to these events and
     * expect to be called like a symfony event listener.
     *
     * @param string $eventName
     * @param Event $event
     */
    final protected function doDispatch($eventName, Event $event)
    {
        $listeners = $this->dispatcher->getListeners($eventName);
        foreach ($listeners as $listener) {
            try {
                call_user_func($listener, $event, $this->pbjx);
            } catch (\Exception $e) {
                if ($event instanceof EventExecutionFailed) {
                    $this->locator->getExceptionHandler()->onEventBusException(
                        new BusExceptionEvent($event, $e)
                    );
                    return;
                }

                $failedEvent = EventExecutionFailedV1::create()
                    ->set('failed_event', $event)
                    ->set('reason', ClassUtils::getShortName($e) . '::' . $e->getMessage());

                if ($event->has('correlator')) {
                    $failedEvent->set('correlator', $event->get('correlator'));
                }

                // running in process for now
                $this->receiveEvent($failedEvent);
            }
        }
    }
}
