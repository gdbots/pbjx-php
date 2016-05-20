<?php

namespace Gdbots\Pbjx\EventStore;

use Gdbots\Common\Microtime;
use Gdbots\Common\ToArray;
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
     * @var bool
     */
    private $forward = true;

    /**
     * Whether or not there are more events that could be fetched.
     * @var bool
     */
    private $hasMore = false;

    /**
     * @param Event[] $events
     * @param StreamId $streamId
     * @param bool $forward
     * @param bool $hasMore
     */
    public function __construct(array $events = [], StreamId $streamId = null, $forward = true, $hasMore = false)
    {
        $this->events = $events;
        $this->streamId = $streamId;
        $this->forward = $forward;
        $this->hasMore = $hasMore;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return [
            'events' => $this->events,
            'stream_id' => (string)$this->streamId ?: null,
            'forward' => $this->forward,
            'has_more' => $this->hasMore,
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
    public function getLastMicrotime()
    {
        $event = end($this->events);
        reset($this->events);
        return $event instanceof Event ? $event->get('occurred_at') : null;
    }
}
