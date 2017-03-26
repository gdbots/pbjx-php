<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Mixin\Response\Response;

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
    public function sendCommand(Command $command): void;

    /**
     * Sends an event via the transport.
     *
     * @param Event $event
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function sendEvent(Event $event): void;

    /**
     * Sends a request via the transport.
     *
     * @param Request $request
     *
     * @return Response
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function sendRequest(Request $request): Response;
}
