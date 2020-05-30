<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Trait;

final class FailingEvent extends AbstractMessage
{
    const EVENT_ID_FIELD = 'event_id';

    use EventV1Trait;

    protected static function defineSchema(): Schema
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:failing-event:1-0-0', __CLASS__,
            array_merge(EventV1Mixin::getFields(), [
                Fb::create('name', T\StringType::create())->build(),
            ]),
            [
                EventV1Mixin::SCHEMA_CURIE_MAJOR,
                EventV1Mixin::SCHEMA_CURIE,
            ]
        );

        MessageResolver::registerSchema($schema);
        return $schema;
    }
}
