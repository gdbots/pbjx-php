<?php

namespace Gdbots\Pbjx\EventStore;

use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;

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
     * In some cases you want to disable 2pc (imports for example).  In this scenario
     * set "DISABLE_PBJX_2PC_EVENT_STORE" env variable before running your symfony command.
     *
     * DISABLE_PBJX_2PC_EVENT_STORE=1 php bin/console --env=prod some-command
     *
     * @var bool
     */
    protected $disabled = false;

    /**
     * @param Pbjx $pbjx
     * @param EventStore $next
     * @param bool $disabled
     */
    public function __construct(Pbjx $pbjx, EventStore $next, $disabled = false)
    {
        $this->pbjx = $pbjx;
        $this->next = $next;
        $this->disabled = filter_var($disabled, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * {@inheritdoc}
     */
    public function putEvents(StreamId $streamId, array $events, array $hints = [], $expectedEtag = null)
    {
        $this->next->putEvents($streamId, $events, $hints, $expectedEtag);
        if ($this->disabled) {
            return;
        }

        /** @var Event $event */
        foreach ($events as $event) {
            $this->pbjx->publish($event->freeze());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEvents(StreamId $streamId, Microtime $since = null, $count = 25, $forward = true, array $hints = [])
    {
        return $this->next->getEvents($streamId, $since, $count, $forward, $hints);
    }

    /**
     * {@inheritdoc}
     */
    public function streamEvents(StreamId $streamId, Microtime $since = null, array $hints = [])
    {
        return $this->next->streamEvents($streamId, $since, $hints);
    }

    /**
     * {@inheritdoc}
     */
    public function streamAllEvents(\Closure $callback, Microtime $since = null, Microtime $until = null, array $hints = [])
    {
        $this->next->streamAllEvents($callback, $since, $until, $hints);
    }
}
