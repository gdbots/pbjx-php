<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbj\Mixin\Response;

class PostResponseEvent extends PbjxEvent
{
    /** @var Request */
    protected $message;

    /** @var Response */
    protected $response;

    /**
     * @param Request $request
     * @param Response $response
     */
    public function __construct(Request $request, Response $response)
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
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }
}
