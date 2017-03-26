<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx\EventStore;

use Gdbots\Pbj\Serializer\PhpArraySerializer;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Indexed\Indexed;
use Gdbots\Schemas\Pbjx\StreamId;

/**
 * EventStore which runs entirely in memory, typically used for unit tests.
 */
final class InMemoryEventStore implements EventStore
{
    /** @var PhpArraySerializer */
    private $serializer;

    /** @var Pbjx */
    private $pbjx;

    /**
     * Array of events keyed by their StreamId.
     *
     * @var array
     */
    private $streams = [];

    /**
     * @param Pbjx $pbjx
     * @param array $streams
     */
    public function __construct(Pbjx $pbjx, array $streams = [])
    {
        $this->pbjx = $pbjx;

        foreach ($streams as $streamId => $events) {
            $this->streams[$streamId] = [];

            foreach ($events as $event) {
                try {
                    if (!$event instanceof Event) {
                        $event = $this->createEventFromArray($event);
                    }

                    $this->streams[$streamId][(string)$event->get('occurred_at')] = $event;
                } catch (\Exception $e) {
                }
            }

            ksort($this->streams[$streamId]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createStorage(array $context = []): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function describeStorage(array $context = []): string
    {
        $count = count($this->streams);
        $streamIds = implode(PHP_EOL, array_keys($this->streams));
        return <<<TEXT
InMemoryEventStore

Count: {$count}
Streams:
{$streamIds}

TEXT;
    }

    /**
     * {@inheritdoc}
     */
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

        /** @var Event $event */
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

    /**
     * {@inheritdoc}
     */
    public function putEvents(StreamId $streamId, array $events, ?string $expectedEtag = null, array $context = []): void
    {
        if (!count($events)) {
            // ignore empty events array
            return;
        }

        if (null !== $expectedEtag) {
            //todo: implement check for in memory
            //$this->optimisticCheck($streamId, $expectedEtag, $context);
        }

        $key = $streamId->toString();
        if (!isset($this->streams[$key])) {
            $this->streams[$key] = [];
        }

        /** @var Event[] $events */
        foreach ($events as $event) {
            $this->pbjx->triggerLifecycle($event);
            $this->streams[$key][(string)$event->get('occurred_at')] = $event;
        }

        ksort($this->streams[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function pipeEvents(StreamId $streamId, callable $receiver, ?Microtime $since = null, ?Microtime $until = null, array $context = []): void
    {
        $reindexing = filter_var($context['reindexing'] ?? false, FILTER_VALIDATE_BOOLEAN);

        do {
            $slice = $this->getStreamSlice($streamId, $since, 100, true, false, $context);
            $since = $slice->getLastOccurredAt();

            foreach ($slice as $event) {
                if (null !== $until && $event->get('occurred_at')->toFloat() >= $until->toFloat()) {
                    return;
                }

                if ($reindexing && !$event instanceof Indexed) {
                    continue;
                }

                $receiver($event, $streamId);
            }
        } while ($slice->hasMore());
    }

    /**
     * {@inheritdoc}
     */
    public function pipeAllEvents(callable $receiver, ?Microtime $since = null, ?Microtime $until = null, array $context = []): void
    {
        foreach (array_keys($this->streams) as $streamId) {
            $this->pipeEvents(StreamId::fromString($streamId), $receiver, $since, $until, $context);
        }
    }

    /**
     * Clears the streams stored on the instance.
     */
    public function clear(): void
    {
        $this->streams = [];
    }

    /**
     * @param array $data
     *
     * @return Event
     */
    private function createEventFromArray(array $data = []): Event
    {
        if (null === $this->serializer) {
            $this->serializer = new PhpArraySerializer();
        }

        /** @var Event $event */
        $event = $this->serializer->deserialize($data);
        return $event;
    }
}
