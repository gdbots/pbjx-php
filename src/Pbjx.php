<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\InvalidArgumentException;
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
     * @param Message $message
     * @param string $suffix
     * @param PbjxEvent $event
     *
     * @throws GdbotsPbjxException
     * @throws InvalidArgumentException
     * @throws \Exception
     */
    public function trigger(Message $message, $suffix, PbjxEvent $event = null);

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
