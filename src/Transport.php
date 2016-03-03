<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Schemas\Pbjx\Command\Command;
use Gdbots\Schemas\Pbjx\Event\Event;
use Gdbots\Schemas\Pbjx\Request\Request;
use Gdbots\Schemas\Pbjx\Request\Response;

interface Transport
{
    /**
     * Sends a command via the transport.
     *
     * @param Command $command
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function sendCommand(Command $command);

    /**
     * Sends an event via the transport.
     *
     * @param Event $event
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function sendEvent(Event $event);

    /**
     * Sends a request via the transport.
     *
     * @param Request $request
     * @return Response
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function sendRequest(Request $request);
}
