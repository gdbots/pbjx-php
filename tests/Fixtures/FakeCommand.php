<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\DomainCommand;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Mixin\CommandMixin;
use Gdbots\Pbj\Mixin\CommandTrait;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class FakeCommand extends AbstractMessage implements DomainCommand
{
    use CommandTrait;

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