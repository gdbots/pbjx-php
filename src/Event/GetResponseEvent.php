<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Exception\LogicException;
use Gdbots\Schemas\Pbjx\Mixin\Response\ResponseV1Mixin;

class GetResponseEvent extends PbjxEvent
{
    protected Message $message;
    protected ?Message $response = null;

    public function __construct(Message $request)
    {
        parent::__construct($request);
    }

    public function getRequest(): Message
    {
        return $this->message;
    }

    public function hasResponse(): bool
    {
        return null !== $this->response;
    }

    public function getResponse(): Message
    {
        return $this->response;
    }

    public function setResponse(Message $response): void
    {
        if ($this->hasResponse()) {
            throw new LogicException('Response can only be set one time.');
        }

        if (!$response->has(ResponseV1Mixin::CTX_REQUEST_FIELD)) {
            $response->set(ResponseV1Mixin::CTX_REQUEST_FIELD, $this->message);
        }

        if (!$response->has(ResponseV1Mixin::CTX_REQUEST_REF_FIELD)) {
            $response->set(ResponseV1Mixin::CTX_REQUEST_REF_FIELD, $this->message->generateMessageRef());
        }

        if (!$response->has(ResponseV1Mixin::CTX_CORRELATOR_REF_FIELD)
            && $this->message->has(ResponseV1Mixin::CTX_CORRELATOR_REF_FIELD)
        ) {
            $response->set(
                ResponseV1Mixin::CTX_CORRELATOR_REF_FIELD,
                $this->message->get(ResponseV1Mixin::CTX_CORRELATOR_REF_FIELD)
            );
        }

        if (!$response->has(ResponseV1Mixin::CTX_TENANT_ID_FIELD)
            && $this->message->has(ResponseV1Mixin::CTX_TENANT_ID_FIELD)
        ) {
            $response->set(
                ResponseV1Mixin::CTX_TENANT_ID_FIELD,
                $this->message->get(ResponseV1Mixin::CTX_TENANT_ID_FIELD)
            );
        }

        $this->response = $response;
        $this->stopPropagation();
    }

    public function supportsRecursion(): bool
    {
        return false;
    }
}
