<?php

namespace Gdbots\Pbjx\Exception;

use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbjx\Request\RequestFailedResponse;

class RequestHandlingFailed extends \RuntimeException implements GdbotsPbjxException
{
    /** @var RequestFailedResponse */
    protected $response;

    /**
     * @param RequestFailedResponse $response
     */
    public function __construct(RequestFailedResponse $response)
    {
        $this->response = $response;
        $ref = $response->getRequestRef() ?: $response->getFailedRequest()->getRequestId();
        parent::__construct(
            sprintf(
                'Request [%s] could not be handled.  Reason: %s', $ref, $this->response->getReason()
            )
        );
    }

    /**
     * @return RequestFailedResponse
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return DomainRequest
     */
    public function getRequest()
    {
        return $this->response->getFailedRequest();
    }
}
