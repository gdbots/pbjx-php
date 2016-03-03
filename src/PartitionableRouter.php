<?php

namespace Gdbots\Pbjx;

use Gdbots\Schemas\Pbjx\Command\Command;
use Gdbots\Schemas\Pbjx\Event\Event;
use Gdbots\Schemas\Pbjx\Request\Request;

/**
 * A transport that supports partitioning will route messages
 * to a partition based on the message content.  This is in
 * addition to the channel the base router provides.
 *
 * For example, all commands go to channel "command_stream"
 * and messages are partitioned on "command_id" with mod 8.
 */
interface PartitionableRouter extends Router
{
    /**
     * @param Command $command
     * @return string
     */
    public function partitionForCommand(Command $command);

    /**
     * @param Event $event
     * @return string
     */
    public function partitionForEvent(Event $event);

    /**
     * @param Request $request
     * @return string
     */
    public function partitionForRequest(Request $request);
}
