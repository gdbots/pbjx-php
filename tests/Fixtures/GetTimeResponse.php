<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;
use Gdbots\Schemas\Pbjx\Request\ResponseV1;
use Gdbots\Schemas\Pbjx\Request\ResponseV1Mixin;
use Gdbots\Schemas\Pbjx\Request\ResponseV1Trait;

final class GetTimeResponse extends AbstractMessage implements ResponseV1
{
    use ResponseV1Trait;

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:get-time-response:1-0-0', __CLASS__,
            [
                Fb::create('time', T\DateTimeType::create())->build()
            ],
            [
                ResponseV1Mixin::create()
            ]
        );

        MessageResolver::registerSchema($schema);
        return $schema;
    }
}
