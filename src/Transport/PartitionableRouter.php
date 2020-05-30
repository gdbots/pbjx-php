<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Message;

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
    public function partitionForCommand(Message $command): string;

    public function partitionForEvent(Message $event): string;

    public function partitionForRequest(Message $request): string;
}
