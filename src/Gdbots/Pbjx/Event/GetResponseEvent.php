<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\HasCorrelator;
use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbj\Mixin\Response;
use Gdbots\Pbjx\Exception\LogicException;

class GetResponseEvent extends PbjxEvent
{
    /** @var Request */
    protected $message;

    /** @var Response */
    protected $response;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * @return Request
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
            throw new LogicException('Response can only be set one time.');
        }

        // todo: review we're setting request_id here and in request bus and some in default pbjx.
        $this->response = $response->setRequestId($this->message->getRequestId());

        if ($this->message instanceof HasCorrelator && $response instanceof HasCorrelator) {
            $response->correlateWith($this->message);
        }

        $this->stopPropagation();
    }
}
