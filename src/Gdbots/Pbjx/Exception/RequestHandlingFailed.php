<?php

namespace Gdbots\Pbjx\Exception;

use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbjx\Domain\Response\RequestHandlingFailedV1;

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
        parent::__construct(
            sprintf(
                'Request with id [%s] could not be handled.  Reason: %s',
                $this->response->getRequestId(),
                $this->response->getReason()
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
