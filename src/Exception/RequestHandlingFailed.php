<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

use Gdbots\Pbj\Message;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Request\RequestV1Mixin;
use Gdbots\Schemas\Pbjx\Request\RequestFailedResponseV1;

final class RequestHandlingFailed extends \RuntimeException implements GdbotsPbjxException, \JsonSerializable
{
    private Message $response;

    public function __construct(Message $response)
    {
        $this->response = $response;
        $ref = $response->get(RequestFailedResponseV1::CTX_REQUEST_REF_FIELD)
            ?: $response->get(RequestFailedResponseV1::CTX_REQUEST_FIELD)->get(RequestV1Mixin::REQUEST_ID_FIELD);
        parent::__construct(
            sprintf(
                'Request [%s] could not be handled.  %s::%s::%s',
                $ref,
                $this->response->get(RequestFailedResponseV1::ERROR_NAME_FIELD),
                $this->response->get(RequestFailedResponseV1::ERROR_CODE_FIELD),
                $this->response->get(RequestFailedResponseV1::ERROR_MESSAGE_FIELD)
            ),
            $this->response->get(RequestFailedResponseV1::ERROR_CODE_FIELD, Code::UNKNOWN)
        );
    }

    public function getResponse(): Message
    {
        return $this->response;
    }

    public function getRequest(): ?Message
    {
        return $this->response->get(RequestFailedResponseV1::CTX_REQUEST_FIELD);
    }

    public function jsonSerialize()
    {
        return $this->response->toArray();
    }

    public function __toString()
    {
        return json_encode($this->response, JSON_PRETTY_PRINT);
    }
}
