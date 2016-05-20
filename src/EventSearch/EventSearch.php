<?php

namespace Gdbots\Pbjx\EventStore;

use Gdbots\Common\Microtime;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\OptimisticCheckFailed;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;

interface EventSearch
{
    /**
     * Appends an array of events to a stream.
     *
     * @param string $streamId
     * @param Event[] $events
     *
     * @throws OptimisticCheckFailed
     * @throws GdbotsPbjxException
     */
    public function index($streamId, array $events);
}
