<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\InvalidArgumentException;
use Gdbots\Pbjx\Exception\TooMuchRecursion;
use Gdbots\Schemas\Pbjx\Command\Command;
use Gdbots\Schemas\Pbjx\Event\Event;
use Gdbots\Schemas\Pbjx\Request\Request;
use Gdbots\Schemas\Pbjx\Request\Response;

interface Pbjx
{
    /**
     * Triggers in-process events using the dispatcher which will announce an event for each of:
     *
     * curie:v[MAJOR VERSION].suffix
     * curie.suffix
     * mixinId.suffix (mixinId is the mixin with the major rev)
     * mixinCurie.suffix (mixinCurie is the curie ONLY)
     *
     * When the recursive option is used, any fields with MessageType will also be run through
     * the trigger process.  The PbjxEvent object will have a reference to the parent event
     * and the depth of the recursion.
     *
     * @param Message $message
     * @param string $suffix
     * @param PbjxEvent $event
     * @param bool $recursive   If true, all field values with MessageType are also triggered.
     *
     * @return Pbjx
     *
     * @throws GdbotsPbjxException
     * @throws InvalidArgumentException
     * @throws TooMuchRecursion
     * @throws \Exception
     */
    public function trigger(Message $message, $suffix, PbjxEvent $event = null, $recursive = true);

    /**
     * Runs the "standard" lifecycle for a message prior to send, publish or request.
     * Internally this is a call to Pbjx::trigger for suffixes bind, validate and enrich.
     *
     * After the lifecycle completes the message should be ready to be sent via a transport.
     *
     * @param Message $message
     * @param PbjxEvent $event
     *
     * @return Pbjx
     *
     * @throws \Exception
     */
    public function triggerLifecycle(Message $message, PbjxEvent $event = null);

    /**
     * Copies context fields (ip, user agent, correlator, etc.) from one message to another.
     *
     * @param Message $from
     * @param Message $to
     *
     * @return Pbjx
     */
    public function copyContext(Message $from, Message $to);

    /**
     * Processes a command asynchronously.
     *
     * @param Command $command
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function send(Command $command);

    /**
     * Publishes events to all subscribers.
     *
     * @param Event $event
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function publish(Event $event);

    /**
     * Processes a request synchronously and returns the response.
     *
     * @param Request $request
     * @return Response
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function request(Request $request);
}
