<?php

namespace Gdbots\Pbjx\Exception;

use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbjx\Domain\Request\RequestHandlingFailedV1;

class RequestHandlingFailed extends \RuntimeException implements GdbotsPbjxException
{
    /** @var RequestHandlingFailedV1 */
    protected $response;

    /**
     * @param RequestHandlingFailedV1 $response
     */
    public function __construct(RequestHandlingFailedV1 $response)
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
     * @return Request
     */
    public function getRequest()
    {
        return $this->response->getFailedRequest();
    }
}
