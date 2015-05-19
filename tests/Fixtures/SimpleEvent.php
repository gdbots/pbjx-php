<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\DomainEvent;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Mixin\EventMixin;
use Gdbots\Pbj\Mixin\EventTrait;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class SimpleEvent extends AbstractMessage implements DomainEvent
{
    use EventTrait;

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:simple-event:1-0-0', __CLASS__,
            [
                Fb::create('name', T\StringType::create())->build(),
            ],
            [
                EventMixin::create()
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
        return $this->get('name');
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        return $this->setSingleValue('name', $name);
    }
}