<?php

namespace Gdbots\Pbjx\Domain\Request;

use Gdbots\Pbj\Mixin\AbstractResponse;
use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\Mixin\ResponseMixin;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class RequestHandlingFailedV1 extends AbstractResponse
{
    const FAILED_REQUEST_FIELD_NAME = 'failed_request';
    const REASON_FIELD_NAME = 'reason';

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        return new Schema('pbj:gdbots:pbjx:response:request-handling-failed:1-0-0', __CLASS__,
            [
                Fb::create(self::FAILED_REQUEST_FIELD_NAME, T\MessageType::create())
                    ->required()
                    ->anyOfClassNames(['Gdbots\Pbj\Mixin\Request'])
                    ->build(),
                Fb::create(self::REASON_FIELD_NAME, T\TextType::create())
                    ->build(),
            ],
            [
                ResponseMixin::create()
            ]
        );
    }

    /**
     * @return Request
     */
    public function getFailedRequest()
    {
        return $this->get(self::FAILED_REQUEST_FIELD_NAME);
    }

    /**
     * @param Request $request
     * @return self
     */
    public function setFailedRequest(Request $request)
    {
        return $this->setSingleValue(self::FAILED_REQUEST_FIELD_NAME, $request);
    }

    /**
     * @return string
     */
    public function getReason()
    {
        return $this->get(self::REASON_FIELD_NAME);
    }

    /**
     * @param string $reason
     * @return self
     */
    public function setReason($reason)
    {
        return $this->setSingleValue(self::REASON_FIELD_NAME, $reason);
    }
}
