<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;

interface RequestBus
{
    /**
     * Processes a request and returns the response from the handler.
     *
     * @param Message $request
     *
     * @return Message
     */
    public function request(Message $request): Message;

    /**
     * Processes a request directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @param Message $request
     *
     * @return Message
     *
     * @internal
     */
    public function receiveRequest(Message $request): Message;
}
