<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Scheduler;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface Scheduler
{
    /**
     * Creates the storage for the Scheduler.
     *
     * @param array $context Data that helps the implementation decide where to create the storage.
     */
    public function createStorage(array $context = []): void;

    /**
     * Returns debugging information about the storage for the Scheduler.
     *
     * @param array $context Data that helps the implementation decide what storage to describe.
     *
     * @return string
     */
    public function describeStorage(array $context = []): string;

    /**
     * Schedules a command to send at a later time.
     *
     * @param Message     $command   The command to send.
     * @param int         $timestamp Unix timestamp (in the future) when the command should be sent.
     * @param string|null $jobId     Optional identifier for the job (existing job with the same id will be canceled).
     * @param array       $context   Data that helps the Scheduler decide where to read/write data from.
     *
     * @return string Returns the jobId (can be used for cancellation)
     *
     * @throws GdbotsPbjxException
     * @throws \Throwable
     */
    public function sendAt(Message $command, int $timestamp, ?string $jobId = null, array $context = []): string;

    /**
     * Cancels previously scheduled commands by their job ids.
     *
     * @param string[] $jobIds
     * @param array    $context Data that helps the Scheduler decide where to read/write data from.
     *
     * @throws GdbotsPbjxException
     * @throws \Throwable
     */
    public function cancelJobs(array $jobIds, array $context = []): void;
}
