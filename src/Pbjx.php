<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\EventStore\EventStore;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\InvalidArgumentException;
use Gdbots\Pbjx\Exception\TooMuchRecursion;

interface Pbjx
{
    /**
     * Triggers lifecycle events using the dispatcher which will announce an event for each of:
     *
     * gdbots_pbjx.message.suffix
     * mixin:v[MAJOR VERSION].suffix
     * mixin.suffix
     * curie:v[MAJOR VERSION].suffix
     * curie.suffix
     *
     * When the recursive option is used, any fields with MessageType will also be run through
     * the trigger process.  The PbjxEvent object will have a reference to the parent event
     * and the depth of the recursion.
     *
     * @param Message        $message   The message that will be processed.
     * @param string         $suffix    A string indicating the lifecycle phase (bind, validate, enrich, etc.)
     * @param PbjxEvent|null $event     An event object containing the message.
     * @param bool           $recursive If true, all field values with MessageType are also triggered.
     * @param bool           $throw     If true, exceptions are thrown, otherwise they are logged.
     *
     * @return static
     *
     * @throws GdbotsPbjxException
     * @throws InvalidArgumentException
     * @throws TooMuchRecursion
     * @throws \Throwable
     */
    public function trigger(Message $message, string $suffix, ?PbjxEvent $event = null, bool $recursive = true, bool $throw = true): static;

    /**
     * Runs the "standard" lifecycle for a message prior to send, publish or request.
     * Internally this is a call to Pbjx::trigger for suffixes bind, validate and enrich.
     *
     * After the lifecycle completes the message should be ready to be sent via a transport
     * or frozen and persisted to storage.
     *
     * @param Message $message
     * @param bool    $recursive
     *
     * @return static
     *
     * @throws \Throwable
     */
    public function triggerLifecycle(Message $message, bool $recursive = true): static;

    /**
     * Copies context fields (ip, user agent, correlator, etc.) from one message to another.
     *
     * @param Message $from
     * @param Message $to
     *
     * @return static
     */
    public function copyContext(Message $from, Message $to): static;

    /**
     * Processes a command asynchronously.
     *
     * @param Message $command
     *
     * @throws GdbotsPbjxException
     * @throws \Throwable
     */
    public function send(Message $command): void;

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

    /**
     * Publishes events to all subscribers.
     *
     * @param Message $event
     *
     * @throws GdbotsPbjxException
     * @throws \Throwable
     */
    public function publish(Message $event): void;

    /**
     * Processes a request synchronously and returns the response.
     *
     * @param Message $request
     *
     * @return Message
     *
     * @throws GdbotsPbjxException
     * @throws \Throwable
     */
    public function request(Message $request): Message;

    public function getEventStore(): EventStore;

    public function getEventSearch(): EventSearch;
}
