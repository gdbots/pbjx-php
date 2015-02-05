<?php

namespace Gdbots\Pbjx\Requestbus;

interface RequestBusInterface
{
    /**
     * Processes a request and returns the response from
     * the handler.
     *
     * @param RequestInterface $request
     * @return mixed
     *
     * @throws \Exception
     */
    public function request(RequestInterface $request);

    /**
     * Processes a request directly.  DO NOT use this method in
     * the application as this is intended for the consumers
     * and workers of the messaging system.
     *
     * @param RequestInterface $request
     * @return mixed
     *
     * @throws \Exception
     */
    public function receiveRequest(RequestInterface $request);
}