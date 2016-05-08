<?php

namespace Gdbots\Pbjx\EventStore;

use Gdbots\Common\Microtime;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\OptimisticCheckFailed;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;

interface EventStore
{
    /**
     * Appends an array of events to a stream.
     *
     * @param string $streamId
     * @param Event[] $events
     *
     * @throws OptimisticCheckFailed
     * @throws GdbotsPbjxException
     */
    public function putEvents($streamId, array $events);

    /**
     * Returns a collection of events in the given stream that are greater than
     * (or less than if using forward=false) the start time.  The time is compared
     * against the event's "occurred_at" field and the results are sorted by it.
     *
     * A collection will always be returned, even when empty or when the stream doesn't exist.
     *
     * @param string $streamId
     * @param Microtime $start
     * @param int $count
     * @param bool $forward
     *
     * @return EventCollection
     *
     * @throws GdbotsPbjxException
     */
    public function getEvents($streamId, Microtime $start = null, $count = 25, $forward = true);

    /**
     * Returns a generator which yields all events (forward only) from the beginning
     * of the stream or the start time provided.
     *
     * @param string $streamId
     * @param Microtime $start
     *
     * @return \Generator
     *
     * @throws GdbotsPbjxException
     */
    public function streamEvents($streamId, Microtime $start = null);
}
