<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\ClassUtil;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Transport\Transport;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Event\EventExecutionFailedV1;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class SimpleEventBus implements EventBus
{
    private EventDispatcherInterface $dispatcher;
    private ServiceLocator $locator;
    private Transport $transport;
    private Pbjx $pbjx;

    public function __construct(ServiceLocator $locator, Transport $transport)
    {
        $this->locator = $locator;
        $this->transport = $transport;
        $this->dispatcher = $this->locator->getDispatcher();
        $this->pbjx = $this->locator->getPbjx();
    }

    public function publish(Message $event): void
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
    public function receiveEvent(Message $event): void
    {
        $event->freeze();
        $schema = $event::schema();
        $curie = $schema->getCurie();

        foreach ($schema->getMixins() as $mixin) {
            $this->dispatch($event, $mixin);
        }

        $this->dispatch($event, $schema->getCurieMajor());
        $this->dispatch($event, $curie->toString());
        $this->dispatch($event, "{$curie->getVendor()}:{$curie->getPackage()}:*");
        $this->dispatch($event, '*');
    }

    /**
     * todo: need to decouple this dispatch/event subscribing from symfony since our process doesn't
     * publish events with an Event object and you cannot stop propagation.
     *
     * this is left for now with the expectation that we won't subscribe to these events and
     * expect to be called like a symfony event listener.
     *
     * @param Message $event
     * @param string  $eventName
     */
    private function dispatch(Message $event, string $eventName): void
    {
        foreach ($this->dispatcher->getListeners($eventName) as $listener) {
            try {
                call_user_func($listener, $event, $this->pbjx);
            } catch (\Throwable $e) {
                if ($event instanceof EventExecutionFailedV1) {
                    $this->locator->getExceptionHandler()->onEventBusException(new BusExceptionEvent($event, $e));
                    return;
                }

                $code = $e->getCode() > 0 ? $e->getCode() : Code::UNKNOWN->value;

                $failedEvent = EventExecutionFailedV1::create()
                    ->set('event', $event)
                    ->set('error_code', $code)
                    ->set('error_name', ClassUtil::getShortName($e))
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
