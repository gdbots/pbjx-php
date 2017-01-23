<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\EventStore\EventStore;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\InvalidArgumentException;
use Gdbots\Pbjx\Exception\TooMuchRecursion;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Mixin\Response\Response;

interface Pbjx
{
    /**
     * Triggers lifecycle events using the dispatcher which will announce an event for each of:
     *
     * gdbots_pbjx.message.suffix
     * curie:v[MAJOR VERSION].suffix
     * curie.suffix
     * mixinId.suffix (mixinId is the mixin with the major rev)
     * mixinCurie.suffix (mixinCurie is the curie ONLY)
     *
     * When the recursive option is used, any fields with MessageType will also be run through
     * the trigger process.  The PbjxEvent object will have a reference to the parent event
     * and the depth of the recursion.
     *
     * @param Message   $message   The message that will be processed.
     * @param string    $suffix    A string indicating the lifecycle phase (bind, validate, enrich, etc.)
     * @param PbjxEvent $event     An event object containing the message.
     * @param bool      $recursive If true, all field values with MessageType are also triggered.
     *
     * @return Pbjx
     *
     * @throws GdbotsPbjxException
     * @throws InvalidArgumentException
     * @throws TooMuchRecursion
     * @throws \Exception
     */
    public function trigger(Message $message, string $suffix, ?PbjxEvent $event = null, bool $recursive = true): Pbjx;

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
     * @return Pbjx
     *
     * @throws \Exception
     */
    public function triggerLifecycle(Message $message, bool $recursive = true): Pbjx;

    /**
     * Copies context fields (ip, user agent, correlator, etc.) from one message to another.
     *
     * @param Message $from
     * @param Message $to
     *
     * @return Pbjx
     */
    public function copyContext(Message $from, Message $to): Pbjx;

    /**
     * Processes a command asynchronously.
     *
     * @param Command $command
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function send(Command $command): void;

    /**
     * Publishes events to all subscribers.
     *
     * @param Event $event
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function publish(Event $event): void;

    /**
     * Processes a request synchronously and returns the response.
     *
     * @param Request $request
     *
     * @return Response
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function request(Request $request): Response;

    /**
     * @return EventStore
     */
    public function getEventStore(): EventStore;

    /**
     * @return EventSearch
     */
    public function getEventSearch(): EventSearch;
}
