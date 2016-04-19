<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Trait;

final class FailingEvent extends AbstractMessage implements EventV1
{
    use EventV1Trait;

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:failing-event:1-0-0', __CLASS__,
            [
                Fb::create('name', T\StringType::create())->build(),
            ],
            [
                EventV1Mixin::create()
            ]
        );

        MessageResolver::registerSchema($schema);
        return $schema;
    }
}
