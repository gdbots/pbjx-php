<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbj\DomainResponse;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface RequestHandler
{
    /**
     * @param DomainRequest $request
     * @param Pbjx $pbjx
     * @return DomainResponse
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleRequest(DomainRequest $request, Pbjx $pbjx);
}
