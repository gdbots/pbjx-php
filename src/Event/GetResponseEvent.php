<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbjx\Exception\LogicException;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Mixin\Response\Response;

class GetResponseEvent extends PbjxEvent
{
    /** @var Request */
    protected $message;

    /** @var Response */
    protected $response;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->message;
    }

    /**
     * @return bool
     */
    public function hasResponse(): bool
    {
        return null !== $this->response;
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * @param Response $response
     *
     * @throws LogicException
     */
    public function setResponse(Response $response): void
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

        $this->response = $response;
        $this->stopPropagation();
    }

    /**
     * @return bool
     */
    public function supportsRecursion(): bool
    {
        return false;
    }
}
