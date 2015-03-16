<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbj\Mixin\Response;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface RequestHandler
{
    /**
     * @param Request $request
     * @param Pbjx $pbjx
     * @return Response
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleRequest(Request $request, Pbjx $pbjx);
}
