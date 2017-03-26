<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx;

use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Mixin\Response\Response;

interface RequestBus
{
    /**
     * Processes a request and returns the response from the handler.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function request(Request $request): Response;

    /**
     * Processes a request directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @internal
     * 
     * @param Request $request
     *
     * @return Response
     */
    public function receiveRequest(Request $request): Response;
}
