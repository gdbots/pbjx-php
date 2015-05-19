<?php

namespace Gdbots\Pbjx\Request;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\Mixin\ResponseMixin;
use Gdbots\Pbj\Mixin\ResponseTrait;
use Gdbots\Pbj\Request;
use Gdbots\Pbj\Response;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class RequestHandlingFailedV1 extends AbstractMessage implements Response
{
    use ResponseTrait;

    const FAILED_REQUEST_FIELD_NAME = 'failed_request';
    const REASON_FIELD_NAME = 'reason';

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        return new Schema('pbj:gdbots:pbjx:response:request-handling-failed:1-0-0', __CLASS__,
            [
                Fb::create('failed_request', T\MessageType::create())
                    ->required()
                    ->className('Gdbots\Pbj\Request')
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
     * @return Request
     */
    public function getFailedRequest()
    {
        return $this->get('failed_request');
    }

    /**
     * @param Request $request
     * @return self
     */
    public function setFailedRequest(Request $request)
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
