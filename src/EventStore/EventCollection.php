<?php

namespace Gdbots\Pbjx\EventStore;

use Gdbots\Common\ToArray;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;

final class EventCollection implements ToArray, \JsonSerializable, \IteratorAggregate, \Countable
{
    /** @var Event[] */
    private $events = [];

    /**
     * The stream this collection belongs to.  A null value means this collection
     * can be from multiple streams (or all of them).
     *
     * @var StreamId
     */
    private $streamId;

    /**
     * The direction this collection was fetched in.
     *
     * @var bool
     */
    private $forward = true;

    /**
     * If a consistent read was used to get the collection.
     *
     * @var bool
     */
    private $consistent = false;

    /**
     * Whether or not there are more events that could be fetched.
     *
     * @var bool
     */
    private $hasMore = false;

    /**
     * @param Event[]  $events     An array of events.
     * @param StreamId $streamId   The id of the stream to read from, e.g. "article:1234"
     * @param bool     $forward    When true, the events are read from oldest to newest, otherwise newest to oldest.
     * @param bool     $consistent An eventually consistent read was used to get this collection.
     * @param bool     $hasMore    True if there are more events in the stream.
     */
    public function __construct(
        array $events = [],
        StreamId $streamId = null,
        $forward = true,
        $consistent = false,
        $hasMore = false
    )
    {
        $this->events = $events;
        $this->streamId = $streamId;
        $this->forward = $forward;
        $this->consistent = $consistent;
        $this->hasMore = $hasMore;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return [
            'events'     => $this->events,
            'stream_id'  => (string) $this->streamId ?: null,
            'forward'    => $this->forward,
            'consistent' => $this->consistent,
            'has_more'   => $this->hasMore,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->events);
    }

    /**
     * @return bool
     */
    public function hasStreamId()
    {
        return null !== $this->streamId;
    }

    /**
     * @return StreamId
     */
    public function getStreamId()
    {
        return $this->streamId;
    }

    /**
     * @return bool
     */
    public function isForward()
    {
        return $this->forward;
    }

    /**
     * @return bool
     */
    public function isConsistent()
    {
        return $this->consistent;
    }

    /**
     * @return bool
     */
    public function hasMore()
    {
        return $this->hasMore;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->events);
    }

    /**
     * @return Microtime|null
     */
    public function getLastOccurredAt()
    {
        $event = end($this->events);
        reset($this->events);

        return $event instanceof Event ? $event->get('occurred_at') : null;
    }
}
