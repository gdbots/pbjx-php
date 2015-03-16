<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\Mixin\AbstractCommand;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Mixin\CommandMixin;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class SayHello extends AbstractCommand
{
    const NAME_FIELD_NAME = 'name';

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:say-hello:1-0-0', __CLASS__,
            [
                Fb::create(self::NAME_FIELD_NAME, T\StringType::create())->build(),
            ],
            [
                CommandMixin::create()
            ]
        );

        MessageResolver::registerSchema($schema);
        return $schema;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->get(self::NAME_FIELD_NAME);
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        return $this->setSingleValue(self::NAME_FIELD_NAME, $name);
    }
}