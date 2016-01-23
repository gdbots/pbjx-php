<?php

namespace Gdbots\Pbjx\Exception;

use Gdbots\Schemas\Pbj\Request\Request;
use Gdbots\Schemas\Pbjx\Request\RequestFailedResponse;

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
        $ref = $response->get('request_ref') ?: $response->get('failed_request')->get('request_id');
        parent::__construct(
            sprintf(
                'Request [%s] could not be handled.  Reason: %s', $ref, $this->response->get('reason')
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
     * @return Request
     */
    public function getRequest()
    {
        return $this->response->get('failed_request');
    }
}
