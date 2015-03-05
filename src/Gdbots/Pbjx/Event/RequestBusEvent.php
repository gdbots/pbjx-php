<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbj\Extension\Response;
use Gdbots\Pbjx\Exception\LogicException;

class RequestBusEvent extends PbjxEvent
{
    /** @var Request */
    protected $request;

    /** @var Response */
    protected $response;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return bool
     */
    public function hasResponse()
    {
        return null !== $this->response;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param Response $response
     * @throws LogicException
     */
    public function setResponse(Response $response)
    {
        if ($this->hasResponse()) {
            throw new LogicException('Response can only be set one time on RequestBusEvent.');
        }
        $this->response = $response->setRequestId($this->request->getRequestId());
        $this->stopPropagation();
    }
}
