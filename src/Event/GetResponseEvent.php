<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbj\DomainResponse;
use Gdbots\Pbjx\Exception\LogicException;

class GetResponseEvent extends PbjxEvent
{
    /** @var DomainRequest */
    protected $message;

    /** @var DomainResponse */
    protected $response;

    /**
     * @param DomainRequest $request
     */
    public function __construct(DomainRequest $request)
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
     * @return bool
     */
    public function hasResponse()
    {
        return null !== $this->response;
    }

    /**
     * @return DomainResponse
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param DomainResponse $response
     * @throws LogicException
     */
    public function setResponse(DomainResponse $response)
    {
        if ($this->hasResponse()) {
            throw new LogicException('Response can only be set one time.');
        }

        if (!$response->hasRequestRef()) {
            $response->setRequestRef($this->message->generateMessageRef());
        }

        if (!$response->hasCorrelator() && $this->message->hasCorrelator()) {
            $response->setCorrelator($this->message->getCorrelator());
        }

        $this->response = $response;
        $this->stopPropagation();
    }
}
