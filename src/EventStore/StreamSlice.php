<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventStore;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
use Gdbots\Schemas\Pbjx\StreamId;

final class StreamSlice implements \JsonSerializable, \IteratorAggregate, \Countable
{
    /** @var Message[] */
    private array $events = [];

    /**
     * The stream this collection belongs to. A null value means this collection
     * can be from multiple streams (or all of them).
     */
    private ?StreamId $streamId;

    /**
     * The direction this collection was fetched in.
     */
    private bool $forward = true;

    /**
     * If a consistent read was used to get the collection.
     */
    private bool $consistent = false;

    /**
     * Whether or not there are more events that could be fetched.
     */
    private bool $hasMore = false;

    /**
     * @param Message[] $events     An array of events.
     * @param StreamId  $streamId   The id of the stream the events are from, e.g. "article:1234"
     * @param bool      $forward    When true, the events are read from oldest to newest, otherwise newest to oldest.
     * @param bool      $consistent An eventually consistent read was used to get this slice.
     * @param bool      $hasMore    True if there are more events in the stream.
     */
    public function __construct(array $events = [], ?StreamId $streamId = null, bool $forward = true, bool $consistent = false, bool $hasMore = false)
    {
        $this->events = $events;
        $this->streamId = $streamId;
        $this->forward = $forward;
        $this->consistent = $consistent;
        $this->hasMore = $hasMore;
    }

    public function toArray(): array
    {
        return [
            'events'     => $this->events,
            'stream_id'  => (string)$this->streamId ?: null,
            'forward'    => $this->forward,
            'consistent' => $this->consistent,
            'has_more'   => $this->hasMore,
        ];
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->events);
    }

    public function hasStreamId(): bool
    {
        return null !== $this->streamId;
    }

    public function getStreamId(): ?StreamId
    {
        return $this->streamId;
    }

    public function isForward(): bool
    {
        return $this->forward;
    }

    public function isConsistent(): bool
    {
        return $this->consistent;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    public function count(): int
    {
        return count($this->events);
    }

    public function getLastOccurredAt(): ?Microtime
    {
        $event = end($this->events);
        reset($this->events);

        return $event instanceof Message ? $event->get(EventV1Mixin::OCCURRED_AT_FIELD) : null;
    }
}
