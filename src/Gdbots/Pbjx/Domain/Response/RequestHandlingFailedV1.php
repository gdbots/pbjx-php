<?php

namespace Gdbots\Pbjx\Domain\Response;

use Gdbots\Pbj\Extension\AbstractResponse;
use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbj\Extension\ResponseSchema;
use Gdbots\Pbj\FieldBuilder as Fb;
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
        return ResponseSchema::create(__CLASS__, 'pbj:gdbots:pbjx:response:request-handling-failed:1-0-0', [
            Fb::create(self::FAILED_REQUEST_FIELD_NAME, T\AnyMessageType::create())
                ->required()
                ->build(),
            Fb::create(self::REASON_FIELD_NAME, T\TextType::create())
                ->build(),
        ]);
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
