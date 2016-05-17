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
     * @param string $streamId      The name of the stream to read from, e.g. "article-1234"
     * @param Event[] $events       An array of events.
     * @param array $hints          Allows the event store provider to decide where to read/write data from.
     * @param string $expectedEtag  Used to perform optimistic concurrency check.
     *
     * @throws OptimisticCheckFailed
     * @throws GdbotsPbjxException
     */
    public function putEvents($streamId, array $events, array $hints = [], $expectedEtag = null);

    /**
     * Returns a collection of events in the given stream that are greater than
     * (or less than if using forward=false) the since time.  The time is compared
     * against the event's "occurred_at" field and the results are sorted by it.
     *
     * A collection will always be returned, even when empty or when the stream doesn't exist.
     *
     * @param string $streamId  The name of the stream to read from, e.g. "article-1234"
     * @param Microtime $since  Return events since this time (greater than if forward=true, less than if forward=false)
     * @param int $count        The number of events to return.
     * @param bool $forward     When true, the events are read from oldest to newest, otherwise newest to oldest.
     * @param array $hints      Allows the event store provider to decide where to read/write data from.
     *
     * @return EventCollection
     *
     * @throws GdbotsPbjxException
     */
    public function getEvents($streamId, Microtime $since = null, $count = 25, $forward = true, array $hints = []);

    /**
     * Returns a generator which yields all events (forward only) from the beginning
     * of the stream or the since time provided.
     *
     * @param string $streamId  The name of the stream to read from, e.g. "article-1234"
     * @param Microtime $since  Return events greater than this time.
     * @param array $hints      Allows the event store provider to decide where to read/write data from.
     *
     * @return \Generator
     *
     * @throws GdbotsPbjxException
     */
    public function streamEvents($streamId, Microtime $since = null, array $hints = []);

    /**
     * Returns a generator which yields all events (forward only) from ALL streams.
     * The order of events returned will be ordered per stream but not necessarily
     * globally ordered.
     *
     * @param \Closure $callback The function that will receive "$callback($event, $streamId);"
     * @param Microtime $since   Return events greater than this time.
     * @param array $hints       Allows the event store provider to decide where to read/write data from.
     *
     * @throws GdbotsPbjxException
     */
    public function streamAllEvents(\Closure $callback, Microtime $since = null, array $hints = []);
}
