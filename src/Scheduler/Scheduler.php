<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Scheduler;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface Scheduler
{
    /**
     * Creates the storage for the Scheduler.
     */
    public function createStorage(): void;

    /**
     * Returns debugging information about the storage for the Scheduler.
     *
     * @return string
     */
    public function describeStorage(): string;

    /**
     * Schedules a command to send at a later time.
     *
     * @param Message $command   The command to send.
     * @param int     $timestamp Unix timestamp (in the future) when the command should be sent.
     * @param string  $jobId     Optional identifier for the job (existing job with the same id will be canceled).
     *
     * @return string Returns the jobId (can be used for cancellation)
     *
     * @throws GdbotsPbjxException
     * @throws \Throwable
     */
    public function sendAt(Message $command, int $timestamp, ?string $jobId = null): string;

    /**
     * Cancels previously scheduled commands by their job ids.
     *
     * @param string[] $jobIds
     *
     * @throws GdbotsPbjxException
     * @throws \Throwable
     */
    public function cancelJobs(array $jobIds): void;
}
