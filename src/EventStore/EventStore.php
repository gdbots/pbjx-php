<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventStore;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\WellKnown\Identifier;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Pbjx\Exception\EventNotFound;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\OptimisticCheckFailed;
use Gdbots\Schemas\Pbjx\StreamId;

interface EventStore
{
    /**
     * Creates the storage for the EventStore.
     *
     * @param array $context Data that helps the implementation decide where to create the storage.
     */
    public function createStorage(array $context = []): void;

    /**
     * Returns debugging information about the storage for the EventStore.
     *
     * @param array $context Data that helps the implementation decide what storage to describe.
     *
     * @return string
     */
    public function describeStorage(array $context = []): string;

    /**
     * Returns a single event by its identifier (the "event_id" field on the event).
     *
     * @param Identifier $eventId The id of the event to retrieve from the event store.
     * @param array      $context Data that helps the EventStore decide where to read/write data from.
     *
     * @return Message
     *
     * @throws EventNotFound
     * @throws GdbotsPbjxException
     */
    public function getEvent(Identifier $eventId, array $context = []): Message;

    /**
     * Returns an array of events by their identifier (the "event_id" field on the event).
     *
     * @param Identifier[] $eventIds The ids of the events to retrieve from the EventStore.
     * @param array        $context  Data that helps the EventStore decide where to read/write data from.
     *
     * @return Message[]
     *
     * @throws GdbotsPbjxException
     */
    public function getEvents(array $eventIds, array $context = []): array;

    /**
     * Deletes a single event by its identifier (the "event_id" field on the event).
     *
     * @param Identifier $eventId The id of the event to delete from the event store.
     * @param array      $context Data that helps the EventStore decide where to delete data from.
     *
     * @return void
     *
     * @throws GdbotsPbjxException
     */
    public function deleteEvent(Identifier $eventId, array $context = []): void;

    /**
     * Returns a slice of events from the given stream that are greater than
     * (or less than if using forward=false) the since time.  The time is compared
     * against the event's "occurred_at" field and the results are sorted by it.
     *
     * A StreamSlice will always be returned, even when empty or when the stream doesn't exist.
     *
     * @param StreamId       $streamId   The id of the stream to read from, e.g. "acme:article:1234"
     * @param Microtime|null $since      Return events since this time (exclusive greater than if forward=true, less than if forward=false)
     * @param int            $count      The number of events to return.
     * @param bool           $forward    When true, the events are read from oldest to newest, otherwise newest to oldest.
     * @param bool           $consistent An eventually consistent read is used by default unless this is true.
     * @param array          $context    Data that helps the EventStore decide where to read/write data from.
     *
     * @return StreamSlice
     *
     * @throws GdbotsPbjxException
     */
    public function getStreamSlice(StreamId $streamId, ?Microtime $since = null, int $count = 25, bool $forward = true, bool $consistent = false, array $context = []): StreamSlice;

    /**
     * Appends an array of events to a stream.
     *
     * @param StreamId    $streamId     The id of the stream to append to, e.g. "acme:article:1234"
     * @param Message[]   $events       An array of events to append to the stream.
     * @param string|null $expectedEtag Used to perform optimistic concurrency check.
     * @param array       $context      Data that helps the EventStore decide where to read/write data from.
     *
     * @throws OptimisticCheckFailed
     * @throws GdbotsPbjxException
     */
    public function putEvents(StreamId $streamId, array $events, ?string $expectedEtag = null, array $context = []): void;

    /**
     * Reads events (forward only) from a stream.
     *
     * @param StreamId       $streamId The id of the stream to read from, e.g. "acme:article:1234"
     * @param Microtime|null $since    Return events greater than this time (exclusive).
     * @param Microtime|null $until    Return events less than this time (exclusive).
     * @param array          $context  Data that helps the EventStore decide where to read/write data from.
     *
     * @return \Generator
     *
     * @throws GdbotsPbjxException
     */
    public function pipeEvents(StreamId $streamId, ?Microtime $since = null, ?Microtime $until = null, array $context = []): \Generator;

    /**
     * Reads events (forward only) from ALL streams and yields an array
     * of [$event, $streamId] for each item.
     *
     * IMPORTANT! The order of events returned will be ordered per stream
     * but not necessarily globally ordered.
     *
     * @param Microtime|null $since   Return events greater than this time (exclusive).
     * @param Microtime|null $until   Return events less than this time (exclusive).
     * @param array          $context Data that helps the EventStore decide where to read/write data from.
     *
     * @return \Generator
     *
     * @throws GdbotsPbjxException
     */
    public function pipeAllEvents(?Microtime $since = null, ?Microtime $until = null, array $context = []): \Generator;
}
