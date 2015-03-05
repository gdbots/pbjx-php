<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbj\Extension\Response;

interface RequestBus
{
    /**
     * Processes a request and returns the response from the handler.
     *
     * @param Request $request
     * @param Notifier $notifier
     * @return Response
     */
    public function request(Request $request, Notifier $notifier);

    /**
     * Processes a request directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @param Request $request
     * @param Notifier $notifier
     * @return Response
     */
    public function receiveRequest(Request $request, Notifier $notifier);
}
