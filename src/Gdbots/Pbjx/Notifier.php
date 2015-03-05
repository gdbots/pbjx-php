<?php

namespace Gdbots\Pbjx;

interface Notifier
{
    /**
     * Sends a notification message on a running process.
     * @param mixed $value
     */
    public function notify($value);
}
