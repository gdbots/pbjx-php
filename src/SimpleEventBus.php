<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Transport\Transport;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Event\EventExecutionFailed;
use Gdbots\Schemas\Pbjx\Event\EventExecutionFailedV1;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class SimpleEventBus implements EventBus
{
    /** @var EventDispatcherInterface */
    private $dispatcher;

    /** @var ServiceLocator */
    private $locator;

    /** @var Transport */
    private $transport;

    /** @var Pbjx */
    private $pbjx;

    /**
     * @param ServiceLocator $locator
     * @param Transport      $transport
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
    public function publish(Event $event): void
    {
        $this->transport->sendEvent($event->freeze());
    }

    /**
     * Publishes the event to all subscribers using the dispatcher, which processes
     * events in memory.  If any events throw an exception an EventExecutionFailed
     * event will be published.
     *
     * {@inheritdoc}
     */
    public function receiveEvent(Event $event): void
    {
        $event->freeze();
        $schema = $event::schema();
        $curie = $schema->getCurie();

        $vendor = $curie->getVendor();
        $package = $curie->getPackage();
        $category = $curie->getCategory();

        foreach ($schema->getMixinIds() as $mixinId) {
            $this->dispatch($mixinId, $event);
        }

        foreach ($schema->getMixinCuries() as $mixinCurie) {
            $this->dispatch($mixinCurie, $event);
        }

        $this->dispatch($schema->getCurieMajor(), $event);
        $this->dispatch($curie->toString(), $event);

        $this->dispatch(sprintf('%s:%s:%s:*', $vendor, $package, $category), $event);
        $this->dispatch(sprintf('%s:%s:*', $vendor, $package), $event);
        $this->dispatch(sprintf('%s:*', $vendor), $event);
    }

    /**
     * todo: need to decouple this dispatch/event subscribing from symfony since our process doesn't
     * publish events with an Event object and you cannot stop propagation.
     *
     * this is left for now with the expectation that we won't subscribe to these events and
     * expect to be called like a symfony event listener.
     *
     * @param string $eventName
     * @param Event  $event
     */
    private function dispatch(string $eventName, Event $event): void
    {
        $listeners = $this->dispatcher->getListeners($eventName);
        foreach ($listeners as $listener) {
            try {
                call_user_func($listener, $event, $this->pbjx);
            } catch (\Exception $e) {
                if ($event instanceof EventExecutionFailed) {
                    $this->locator->getExceptionHandler()->onEventBusException(new BusExceptionEvent($event, $e));
                    return;
                }

                $code = $e->getCode() > 0 ? $e->getCode() : Code::UNKNOWN;

                $failedEvent = EventExecutionFailedV1::create()
                    ->set('event', $event)
                    ->set('error_code', $code)
                    ->set('error_name', ClassUtils::getShortName($e))
                    ->set('error_message', substr($e->getMessage(), 0, 2048))
                    ->set('stack_trace', $e->getTraceAsString());

                if ($e->getPrevious()) {
                    $failedEvent->set('prev_error_message', substr($e->getPrevious()->getMessage(), 0, 2048));
                }

                $this->pbjx->copyContext($event, $failedEvent);

                // running in process for now
                $this->receiveEvent($failedEvent);
            }
        }
    }
}
