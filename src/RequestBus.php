<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbj\DomainResponse;

interface RequestBus
{
    /**
     * Processes a request and returns the response from the handler.
     *
     * @param DomainRequest $request
     * @return DomainResponse
     */
    public function request(DomainRequest $request);

    /**
     * Processes a request directly.  DO NOT use this method in the
     * application as this is intended for the transports, consumers
     * and workers of the Pbjx system.
     *
     * @param DomainRequest $request
     * @return DomainResponse
     */
    public function receiveRequest(DomainRequest $request);
}
