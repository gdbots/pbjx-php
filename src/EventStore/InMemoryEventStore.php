<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventStore;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\Serializer\PhpArraySerializer;
use Gdbots\Pbj\WellKnown\Identifier;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Pbjx\Exception\EventNotFound;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Ncr\Mixin\Indexed\IndexedV1Mixin;
use Gdbots\Schemas\Pbjx\StreamId;

/**
 * EventStore which runs entirely in memory, typically used for unit tests.
 */
final class InMemoryEventStore implements EventStore
{
    private ?PhpArraySerializer $serializer = null;
    private Pbjx $pbjx;

    /**
     * Array of events keyed by their StreamId.
     *
     * @var array
     */
    private array $streams = [];

    /**
     * Array of events keyed by their eventId.
     *
     * @var Message[]
     */
    private array $events = [];

    public function __construct(Pbjx $pbjx, array $streams = [])
    {
        $this->pbjx = $pbjx;

        foreach ($streams as $streamId => $events) {
            $this->streams[$streamId] = [];

            foreach ($events as $event) {
                try {
                    if (!$event instanceof Message) {
                        $event = $this->createEventFromArray($event);
                    }

                    $this->streams[$streamId][(string)$event->get('occurred_at')] = $event;
                } catch (\Throwable $e) {
                }
            }

            ksort($this->streams[$streamId]);
        }
    }

    public function createStorage(array $context = []): void
    {
    }

    public function describeStorage(array $context = []): string
    {
        $scount = count($this->streams);
        $ecount = count($this->events);
        $streamIds = implode(PHP_EOL, array_keys($this->streams));
        return <<<TEXT
InMemoryEventStore

Stream Count: {$scount}
Event Count: {$ecount}
Streams:
{$streamIds}

TEXT;
    }

    public function getEvent(Identifier $eventId, array $context = []): Message
    {
        $key = (string)$eventId;
        if (isset($this->events[$key])) {
            return $this->events[$key];
        }

        throw new EventNotFound();
    }

    public function getEvents(array $eventIds, array $context = []): array
    {
        $keys = array_map('strval', $eventIds);
        return array_intersect_key($this->events, array_flip($keys));
    }

    public function deleteEvent(Identifier $eventId, array $context = []): void
    {
        unset($this->events[(string)$eventId]);
        foreach ($this->streams as $streamId => $stream) {
            /** @var Message $event */
            foreach ($stream as $key => $event) {
                if ($eventId->equals($event->get('event_id'))) {
                    unset($this->streams[$streamId][$key]);
                }
            }
        }
    }

    public function getStreamSlice(StreamId $streamId, ?Microtime $since = null, int $count = 25, bool $forward = true, bool $consistent = false, array $context = []): StreamSlice
    {
        $key = $streamId->toString();
        if (!isset($this->streams[$key])) {
            return new StreamSlice([], $streamId, $forward, $consistent, false);
        }

        $events = $this->streams[$key];
        $i = 0;
        $matched = [];

        if ($forward) {
            $since = null !== $since ? $since->toString() : '0';
        } else {
            krsort($events);
            $since = null !== $since ? $since->toString() : Microtime::create()->toString();
        }

        /** @var Message $event */
        foreach ($events as $event) {
            if ($i >= $count) {
                break;
            }

            $occurredAt = (string)$event->get('occurred_at');

            if ($forward && $occurredAt > $since) {
                $matched[] = $event;
                $i++;
                continue;
            }

            if (!$forward && $occurredAt < $since) {
                $matched[] = $event;
                $i++;
            }
        }

        return new StreamSlice($matched, $streamId, $forward, $consistent, $i >= $count);
    }

    public function putEvents(StreamId $streamId, array $events, ?string $expectedEtag = null, array $context = []): void
    {
        if (!count($events)) {
            // ignore empty events array
            return;
        }

        // todo: implement check for in memory
        /*
        if (null !== $expectedEtag) {
            //$this->optimisticCheck($streamId, $expectedEtag, $context);
        }
        */

        $key = $streamId->toString();
        if (!isset($this->streams[$key])) {
            $this->streams[$key] = [];
        }

        foreach ($events as $event) {
            $this->pbjx->triggerLifecycle($event);
            $this->streams[$key][(string)$event->get('occurred_at')] = $event;
            $this->events[(string)$event->get('event_id')] = $event;
        }

        ksort($this->streams[$key]);
    }

    public function pipeEvents(StreamId $streamId, ?Microtime $since = null, ?Microtime $until = null, array $context = []): \Generator
    {
        $reindexing = filter_var($context['reindexing'] ?? false, FILTER_VALIDATE_BOOLEAN);

        do {
            $slice = $this->getStreamSlice($streamId, $since, 100, true, false, $context);
            $since = $slice->getLastOccurredAt();

            foreach ($slice as $event) {
                if (null !== $until && $event->get('occurred_at')->toFloat() >= $until->toFloat()) {
                    return;
                }

                if ($reindexing && !$event::schema()->hasMixin(IndexedV1Mixin::SCHEMA_CURIE)) {
                    continue;
                }

                yield $event;
            }
        } while ($slice->hasMore());
    }

    public function pipeAllEvents(?Microtime $since = null, ?Microtime $until = null, array $context = []): \Generator
    {
        foreach (array_keys($this->streams) as $id) {
            $streamId = StreamId::fromString($id);
            foreach ($this->pipeEvents($streamId, $since, $until, $context) as $event) {
                yield [$event, $streamId];
            }
        }
    }

    /**
     * Clears the streams stored on the instance.
     */
    public function clear(): void
    {
        $this->streams = [];
        $this->events = [];
    }

    private function createEventFromArray(array $data = []): Message
    {
        if (null === $this->serializer) {
            $this->serializer = new PhpArraySerializer();
        }

        return $this->serializer->deserialize($data);
    }
}
