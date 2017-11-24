<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Request\RequestFailedResponse;

final class RequestHandlingFailed extends \RuntimeException implements GdbotsPbjxException, \JsonSerializable
{
    /** @var RequestFailedResponse */
    private $response;

    /**
     * @param RequestFailedResponse $response
     */
    public function __construct(RequestFailedResponse $response)
    {
        $this->response = $response;
        $ref = $response->get('ctx_request_ref') ?: $response->get('ctx_request')->get('request_id');
        parent::__construct(
            sprintf(
                'Request [%s] could not be handled.  %s::%s::%s',
                $ref,
                $this->response->get('error_name'),
                $this->response->get('error_code'),
                $this->response->get('error_message')
            ),
            $this->response->get('error_code', Code::UNKNOWN)
        );
    }

    /**
     * @return RequestFailedResponse
     */
    public function getResponse(): RequestFailedResponse
    {
        return $this->response;
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->response->get('ctx_request');
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->response->toArray();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->response, JSON_PRETTY_PRINT);
    }
}
