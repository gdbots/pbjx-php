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
}
