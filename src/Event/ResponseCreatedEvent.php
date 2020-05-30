<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;

class ResponseCreatedEvent extends PbjxEvent
{
    protected Message $message;
    protected Message $response;

    public function __construct(Message $request, Message $response)
    {
        parent::__construct($request);
        $this->response = $response;
    }

    public function getRequest(): Message
    {
        return $this->message;
    }

    public function getResponse(): Message
    {
        return $this->response;
    }

    public function supportsRecursion(): bool
    {
        return false;
    }
}
