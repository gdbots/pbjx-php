<?php

namespace Gdbots\Pbjx\Request;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbj\DomainResponse;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\Mixin\ResponseMixin;
use Gdbots\Pbj\Mixin\ResponseTrait;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class RequestHandlingFailedV1 extends AbstractMessage implements DomainResponse
{
    use ResponseTrait;

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        return new Schema('pbj:gdbots:pbjx:response:request-handling-failed:1-0-0', __CLASS__,
            [
                Fb::create('failed_request', T\MessageType::create())
                    ->required()
                    ->className('Gdbots\Pbj\DomainRequest')
                    ->build(),
                Fb::create('reason', T\TextType::create())
                    ->build(),
            ],
            [
                ResponseMixin::create()
            ]
        );
    }

    /**
     * @return bool
     */
    public function hasFailedRequest()
    {
        return $this->has('failed_request');
    }

    /**
     * @return DomainRequest
     */
    public function getFailedRequest()
    {
        return $this->get('failed_request');
    }

    /**
     * @param DomainRequest $request
     * @return self
     */
    public function setFailedRequest(DomainRequest $request)
    {
        return $this->setSingleValue('failed_request', $request);
    }

    /**
     * @return static
     */
    public function clearFailedRequest()
    {
        return $this->clear('failed_request');
    }

    /**
     * @return bool
     */
    public function hasReason()
    {
        return $this->has('reason');
    }

    /**
     * @return string
     */
    public function getReason()
    {
        return $this->get('reason');
    }

    /**
     * @param string $reason
     * @return static
     */
    public function setReason($reason)
    {
        return $this->setSingleValue('reason', $reason);
    }

    /**
     * @return static
     */
    public function clearReason()
    {
        return $this->clear('reason');
    }
}
