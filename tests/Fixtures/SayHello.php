<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Trait;

final class SayHello extends AbstractMessage
{
    const COMMAND_ID_FIELD = 'command_id';

    use CommandV1Trait;

    protected static function defineSchema(): Schema
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:say-hello:1-0-0', __CLASS__,
            array_merge(CommandV1Mixin::getFields(), [
                Fb::create('name', T\StringType::create())->build(),
            ]),
            [
                CommandV1Mixin::SCHEMA_CURIE_MAJOR,
                CommandV1Mixin::SCHEMA_CURIE,
            ]
        );

        MessageResolver::registerSchema($schema);
        return $schema;
    }
}
