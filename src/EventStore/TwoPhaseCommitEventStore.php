<?php

namespace Gdbots\Pbjx\EventStore;

use Gdbots\Common\Microtime;
use Gdbots\Pbjx\Pbjx;

/**
 * If you want events to be published after being stored you can use this
 * class as a decorator to the your event store.
 * @link http://symfony.com/doc/current/components/dependency_injection/advanced.html#decorating-services
 *
 * It is recommended that you have a service reading events from the event
 * store in a separate process (e.g. kinesis stream on dynamodb table).
 * @link http://docs.aws.amazon.com/amazondynamodb/latest/developerguide/Streams.html
 *
 * Useful for debugging, NOT recommended for production as dispatching/publishing
 * might fail even after successful storage of the event.
 */
class TwoPhaseCommitEventStore implements EventStore
{
    /** @var Pbjx */
    protected $pbjx;

    /** @var EventStore */
    protected $next;

    /**
     * @param Pbjx $pbjx
     * @param EventStore $next
     */
    public function __construct(Pbjx $pbjx, EventStore $next)
    {
        $this->pbjx = $pbjx;
        $this->next = $next;
    }

    /**
     * {@inheritdoc}
     */
    public function putEvents($streamId, array $events, array $hints = [], $expectedEtag = null)
    {
        $this->next->putEvents($streamId, $events, $hints, $expectedEtag);
        foreach ($events as $event) {
            $this->pbjx->publish($event);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEvents($streamId, Microtime $since = null, $count = 25, $forward = true, array $hints = [])
    {
        return $this->next->getEvents($streamId, $since, $count, $forward, $hints);
    }

    /**
     * {@inheritdoc}
     */
    public function streamEvents($streamId, Microtime $since = null, array $hints = [])
    {
        return $this->next->streamEvents($streamId, $since, $hints);
    }

    /**
     * {@inheritdoc}
     */
    public function streamAllEvents(\Closure $callback, Microtime $since = null, array $hints = [])
    {
        $this->next->streamAllEvents($callback, $since, $hints);
    }
}
