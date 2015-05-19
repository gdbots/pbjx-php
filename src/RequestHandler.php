<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Request;
use Gdbots\Pbj\Response;
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
