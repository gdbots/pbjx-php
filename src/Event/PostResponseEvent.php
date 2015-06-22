<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbj\DomainResponse;

class PostResponseEvent extends PbjxEvent
{
    /** @var DomainRequest */
    protected $message;

    /** @var DomainResponse */
    protected $response;

    /**
     * @param DomainRequest $request
     * @param DomainResponse $response
     */
    public function __construct(DomainRequest $request, DomainResponse $response)
    {
        parent::__construct($request);
    }

    /**
     * @return DomainRequest
     */
    public function getRequest()
    {
        return $this->message;
    }

    /**
     * @return DomainResponse
     */
    public function getResponse()
    {
        return $this->response;
    }
}
