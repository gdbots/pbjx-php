<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\Mixin\AbstractResponse;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Mixin\ResponseMixin;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class GetTimeResponse extends AbstractResponse
{
    const TIME_FIELD_NAME = 'time';

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:get-time-response:1-0-0', __CLASS__,
            [
                Fb::create(self::TIME_FIELD_NAME, T\DateTimeType::create())->build()
            ],
            [
                ResponseMixin::create()
            ]
        );
        MessageResolver::registerSchema($schema);
        return $schema;
    }

    /**
     * @return \DateTime
     */
    public function getTime()
    {
        return $this->get(self::TIME_FIELD_NAME);
    }

    /**
     * @param \DateTime $time
     * @return self
     */
    public function setTime(\DateTime $time)
    {
        return $this->setSingleValue(self::TIME_FIELD_NAME, $time);
    }
}