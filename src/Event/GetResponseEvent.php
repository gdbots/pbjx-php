<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Exception\LogicException;
use Psr\EventDispatcher\StoppableEventInterface;

class GetResponseEvent extends PbjxEvent implements StoppableEventInterface
{
    protected Message $message;
    protected ?Message $response = null;
    protected bool $propagationStopped = false;

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

        if (!$response->has('ctx_request')) {
            $response->set('ctx_request', $this->message);
        }

        if (!$response->has('ctx_request_ref')) {
            $response->set('ctx_request_ref', $this->message->generateMessageRef());
        }

        if (!$response->has('ctx_correlator_ref') && $this->message->has('ctx_correlator_ref')) {
            $response->set('ctx_correlator_ref', $this->message->get('ctx_correlator_ref'));
        }

        if (!$response->has('ctx_tenant_id') && $this->message->has('ctx_tenant_id')) {
            $response->set('ctx_tenant_id', $this->message->get('ctx_tenant_id'));
        }

        $this->response = $response;
        $this->stopPropagation();
    }

    public function supportsRecursion(): bool
    {
        return false;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
