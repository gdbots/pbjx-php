<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\DependencyInjection\PbjxHandler;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface RequestHandler extends PbjxHandler
{
    /**
     * @param Message $request
     * @param Pbjx    $pbjx
     *
     * @return Message
     *
     * @throws GdbotsPbjxException
     * @throws \Throwable
     */
    public function handleRequest(Message $request, Pbjx $pbjx): Message;
}
