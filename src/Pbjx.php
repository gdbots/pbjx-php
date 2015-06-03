<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\DomainCommand;
use Gdbots\Pbj\DomainEvent;
use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbj\DomainResponse;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Pbjx\Exception\InvalidArgumentException;

interface Pbjx
{
    /**
     * Triggers in-process events using the dispatcher which will announce an event for each of:
     *
     * curie:v[MAJOR VERSION].suffix
     * curie.suffix
     * mixinId.suffix
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
     * @param DomainCommand $command
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function send(DomainCommand $command);

    /**
     * Publishes events to all subscribers.
     *
     * @param DomainEvent $domainEvent
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function publish(DomainEvent $domainEvent);

    /**
     * Processes a request synchronously and returns the response.
     *
     * @param DomainRequest $request
     * @return DomainResponse
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function request(DomainRequest $request);
}
