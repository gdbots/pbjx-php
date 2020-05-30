<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;
use Gdbots\Schemas\Pbjx\Mixin\Request\RequestV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Request\RequestV1Trait;

final class GetTimeRequest extends AbstractMessage
{
    const REQUEST_ID_FIELD = 'request_id';

    use RequestV1Trait;

    protected static function defineSchema(): Schema
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:get-time-request:1-0-0', __CLASS__,
            array_merge(RequestV1Mixin::getFields(), [
                Fb::create('test_fail', T\BooleanType::create())->build(),
            ]),
            [
                RequestV1Mixin::SCHEMA_CURIE_MAJOR,
                RequestV1Mixin::SCHEMA_CURIE,
            ]
        );

        MessageResolver::registerSchema($schema);
        return $schema;
    }
}
