<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Pbjx;
use Symfony\Component\EventDispatcher\Event;

class PbjxEvent extends Event
{
    /** @var Pbjx */
    private static $pbjx;

    /** @var Message */
    protected $message;

    /** @var int */
    protected $depth = 0;

    /** @var static */
    protected $parentEvent;

    /**
     * @param Message $message
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * @return bool
     */
    public static function hasPbjx()
    {
        return null !== self::$pbjx;
    }

    /**
     * @return Pbjx
     */
    public static function getPbjx()
    {
        return self::$pbjx;
    }

    /**
     * @param Pbjx $pbjx
     */
    public static function setPbjx(Pbjx $pbjx)
    {
        self::$pbjx = $pbjx;
    }

    /**
     * @return Message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return int
     */
    public function getDepth()
    {
        return $this->depth;
    }

    /**
     * @return static
     */
    public function hasParentEvent()
    {
        return null !== $this->parentEvent;
    }

    /**
     * @return static
     */
    public function getParentEvent()
    {
        return $this->parentEvent;
    }

    /**
     * @return bool
     */
    public function isRootEvent()
    {
        return 0 === $this->depth;
    }

    /**
     * @param Message $message
     * @return static
     * @throws \LogicException
     */
    public function createChildEvent(Message $message)
    {
        if (!$this->supportsRecursion()) {
            throw new \LogicException(sprintf('%s does not support recursion.', get_called_class()));
        }

        $event = new static($message);
        $event->depth = $this->depth + 1;
        $event->parentEvent = $this;
        return $event;
    }

    /**
     * @return bool
     */
    public function supportsRecursion()
    {
        return true;
    }
}
