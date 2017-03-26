<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Mixin\Response\Response;

interface RequestHandler
{
    /**
     * @param Request $request
     * @param Pbjx    $pbjx
     *
     * @return Response
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleRequest(Request $request, Pbjx $pbjx): Response;
}
