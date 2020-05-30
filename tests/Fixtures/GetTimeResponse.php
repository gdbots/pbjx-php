<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;
use Gdbots\Schemas\Pbjx\Mixin\Response\ResponseV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Response\ResponseV1Trait;

final class GetTimeResponse extends AbstractMessage
{
    const RESPONSE_ID_FIELD = 'response_id';

    use ResponseV1Trait;

    protected static function defineSchema(): Schema
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:get-time-response:1-0-0', __CLASS__,
            array_merge(ResponseV1Mixin::getFields(), [
                Fb::create('time', T\DateTimeType::create())->build(),
            ]),
            [
                ResponseV1Mixin::SCHEMA_CURIE_MAJOR,
                ResponseV1Mixin::SCHEMA_CURIE,
            ]
        );

        MessageResolver::registerSchema($schema);
        return $schema;
    }
}
