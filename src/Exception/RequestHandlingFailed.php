<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

use Gdbots\Pbj\Message;
use Gdbots\Schemas\Pbjx\Enum\Code;

final class RequestHandlingFailed extends \RuntimeException implements GdbotsPbjxException, \JsonSerializable
{
    private Message $response;

    public function __construct(Message $response)
    {
        $this->response = $response;
        $ref = $response->get('ctx_request_ref') ?: $response->get('ctx_request')->get('request_id');
        parent::__construct(
            sprintf(
                'Request [%s] could not be handled. %s::%s::%s',
                $ref,
                $this->response->get('error_name'),
                $this->response->get('error_code'),
                $this->response->get('error_message')
            ),
            $this->response->get('error_code', Code::UNKNOWN->value)
        );
    }

    public function getResponse(): Message
    {
        return $this->response;
    }

    public function getRequest(): ?Message
    {
        return $this->response->get('ctx_request');
    }

    public function jsonSerialize(): array
    {
        return $this->response->toArray();
    }

    public function __toString()
    {
        return json_encode($this->response, JSON_PRETTY_PRINT);
    }
}
