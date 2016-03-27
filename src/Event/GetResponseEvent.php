<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbjx\Exception\LogicException;
use Gdbots\Schemas\Pbjx\Request\Request;
use Gdbots\Schemas\Pbjx\Request\Response;

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

        if (!$response->has('ctx_request_ref')) {
            $response->set('ctx_request_ref', $this->message->generateMessageRef());
        }

        if (!$response->has('ctx_correlator_ref') && $this->message->has('ctx_correlator_ref')) {
            $response->set('ctx_correlator_ref', $this->message->get('ctx_correlator_ref'));
        }

        $this->response = $response;
        $this->stopPropagation();
    }

    /**
     * @return bool
     */
    public function supportsRecursion()
    {
        return false;
    }
}
