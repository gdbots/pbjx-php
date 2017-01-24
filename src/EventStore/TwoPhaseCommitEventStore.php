<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx\EventStore;

use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
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
    /** @var Pbjx */
    private $pbjx;

    /** @var EventStore */
    private $next;

    /**
     * In some cases you want to disable 2pc (imports/replays for example).
     *
     * @var bool
     */
    private $disabled = false;

    /**
     * @param Pbjx       $pbjx
     * @param EventStore $next
     * @param bool       $disabled
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
    public function createStorage(array $context = []): void
    {
        $this->next->createStorage($context);
    }

    /**
     * {@inheritdoc}
     */
    public function describeStorage(array $context = []): string
    {
        return $this->next->describeStorage($context);
    }

    /**
     * {@inheritdoc}
     */
    public function putEvents(StreamId $streamId, array $events, ?string $expectedEtag = null, array $context = []): void
    {
        $this->next->putEvents($streamId, $events, $expectedEtag, $context);
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
    public function getStreamSlice(StreamId $streamId, ?Microtime $since = null, int $count = 25, bool $forward = true, bool $consistent = false, array $context = []): StreamSlice
    {
        return $this->next->getStreamSlice($streamId, $since, $count, $forward, $consistent, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function pipeEvents(StreamId $streamId, callable $receiver, ?Microtime $since = null, ?Microtime $until = null, array $context = []): void
    {
        $this->next->pipeEvents($streamId, $receiver, $since, $until, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function pipeAllEvents(callable $receiver, ?Microtime $since = null, ?Microtime $until = null, array $context = []): void
    {
        $this->next->pipeAllEvents($receiver, $since, $until, $context);
    }
}
