<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Pbjx;

class PbjxEvent
{
    private static ?Pbjx $pbjx = null;
    protected Message $message;
    protected int $depth = 0;
    protected ?self $parentEvent = null;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public static function hasPbjx(): bool
    {
        return null !== self::$pbjx;
    }

    public static function getPbjx(): Pbjx
    {
        return self::$pbjx;
    }

    public static function setPbjx(Pbjx $pbjx): void
    {
        self::$pbjx = $pbjx;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function hasParentEvent(): bool
    {
        return null !== $this->parentEvent;
    }

    public function getParentEvent(): self
    {
        return $this->parentEvent;
    }

    public function isRootEvent(): bool
    {
        return 0 === $this->depth;
    }

    public function createChildEvent(Message $message): self
    {
        if (!$this->supportsRecursion()) {
            throw new \LogicException(sprintf('%s does not support recursion.', static::class));
        }

        $event = new static($message);
        $event->depth = $this->depth + 1;
        $event->parentEvent = $this;
        return $event;
    }

    public function supportsRecursion(): bool
    {
        return true;
    }
}
