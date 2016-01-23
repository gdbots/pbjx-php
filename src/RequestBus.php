<?php

namespace Gdbots\Pbjx;

use Gdbots\Schemas\Pbj\Request\Request;
use Gdbots\Schemas\Pbj\Request\Response;

interface RequestBus
{
    /**
     * Processes a request and returns the response from the handler.
     *
     * @param Request $request
     * @return Response
     */
    public function request(Request $request);

    /**
     * Processes a request directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @param Request $request
     * @return Response
     */
    public function receiveRequest(Request $request);
}
