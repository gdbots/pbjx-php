<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventStore;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\WellKnown\Identifier;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\StreamId;

/**
 * If you want events to be published after being stored you can use this
 * class as a decorator to the EventStore.
 *
 * @link http://symfony.com/doc/current/components/dependency_injection/advanced.html#decorating-services
 *
 * It is recommended that you have a service reading events from the event
 * store in a separate process (e.g. kinesis stream on dynamodb table).
 * @link http://docs.aws.amazon.com/amazondynamodb/latest/developerguide/Streams.html
 */
final class TwoPhaseCommitEventStore implements EventStore
{
    private Pbjx $pbjx;
    private EventStore $next;

    /** In some cases you want to disable 2pc (imports/replays for example). */
    private bool $disabled = false;

    public function __construct(Pbjx $pbjx, EventStore $next, bool $disabled = false)
    {
        $this->pbjx = $pbjx;
        $this->next = $next;
        $this->disabled = $disabled;
    }

    public function createStorage(array $context = []): void
    {
        $this->next->createStorage($context);
    }

    public function describeStorage(array $context = []): string
    {
        return $this->next->describeStorage($context);
    }

    public function getEvent(Identifier $eventId, array $context = []): Message
    {
        return $this->next->getEvent($eventId, $context);
    }

    public function getEvents(array $eventIds, array $context = []): array
    {
        return $this->next->getEvents($eventIds, $context);
    }

    public function deleteEvent(Identifier $eventId, array $context = []): void
    {
        $this->next->deleteEvent($eventId, $context);
    }

    public function getStreamSlice(StreamId $streamId, ?Microtime $since = null, int $count = 25, bool $forward = true, bool $consistent = false, array $context = []): StreamSlice
    {
        return $this->next->getStreamSlice($streamId, $since, $count, $forward, $consistent, $context);
    }

    public function putEvents(StreamId $streamId, array $events, ?string $expectedEtag = null, array $context = []): void
    {
        $this->next->putEvents($streamId, $events, $expectedEtag, $context);
        if ($this->disabled) {
            return;
        }

        /** @var Message $event */
        foreach ($events as $event) {
            $this->pbjx->publish($event->freeze());
        }
    }

    public function pipeEvents(StreamId $streamId, ?Microtime $since = null, ?Microtime $until = null, array $context = []): \Generator
    {
        return $this->next->pipeEvents($streamId, $since, $until, $context);
    }

    public function pipeAllEvents(?Microtime $since = null, ?Microtime $until = null, array $context = []): \Generator
    {
        return $this->next->pipeAllEvents($since, $until, $context);
    }
}
