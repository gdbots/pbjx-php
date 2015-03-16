<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\Mixin\AbstractCommand;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Mixin\CommandMixin;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class FakeCommand extends AbstractCommand
{
    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:fake-command:1-0-0', __CLASS__, [],
            [CommandMixin::create()]
        );
        MessageResolver::registerSchema($schema);
        return $schema;
    }
}