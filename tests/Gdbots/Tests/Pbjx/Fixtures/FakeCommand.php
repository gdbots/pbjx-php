<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\Extension\AbstractCommand;
use Gdbots\Pbj\Extension\CommandSchema;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class FakeCommand extends AbstractCommand
{
    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = CommandSchema::create(__CLASS__, 'pbj:gdbots:tests.pbjx:fixtures:fake-command:1-0-0');
        MessageResolver::registerSchema($schema);
        return $schema;
    }
}