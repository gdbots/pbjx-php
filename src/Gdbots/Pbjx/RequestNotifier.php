<?php

namespace Gdbots\Pbjx;

use React\Promise\Deferred;

final class RequestNotifier implements Notifier
{
    /** @var Deferred */
    private $deferred;

    /**
     * @param Deferred $deferred
     */
    public function __construct(Deferred $deferred = null)
    {
        $this->deferred = $deferred;
    }

    /**
     * {@inheritdoc}
     */
    public function notify($value)
    {
        if (null === $this->deferred) {
            return;
        }
        $this->deferred->notify($value);
    }
}
