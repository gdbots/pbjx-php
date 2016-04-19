<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;
use Gdbots\Schemas\Pbjx\Mixin\Request\RequestV1;
use Gdbots\Schemas\Pbjx\Mixin\Request\RequestV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Request\RequestV1Trait;

final class GetTimeRequest extends AbstractMessage implements RequestV1
{
    use RequestV1Trait;

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:get-time-request:1-0-0', __CLASS__,
            [
                Fb::create('test_fail', T\BooleanType::create())->build(),
            ],
            [
                RequestV1Mixin::create()
            ]
        );

        MessageResolver::registerSchema($schema);
        return $schema;
    }
}
