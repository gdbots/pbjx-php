<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbj\Extension\Response;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface RequestHandler
{
    /**
     * @param Request $request
     * @param Notifier $notifier
     * @param Pbjx $pbjx
     * @return Response
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleRequest(Request $request, Notifier $notifier, Pbjx $pbjx);
}
