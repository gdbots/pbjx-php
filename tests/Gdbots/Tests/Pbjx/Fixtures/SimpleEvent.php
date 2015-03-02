<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\Extension\AbstractEvent;
use Gdbots\Pbj\Extension\EventSchema;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class SimpleEvent extends AbstractEvent
{
    const NAME_FIELD_NAME = 'name';

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = EventSchema::create(__CLASS__, 'pbj:gdbots:tests.pbjx:fixtures:simple-event:1-0-0', [
            Fb::create(self::NAME_FIELD_NAME, T\StringType::create())->build(),
        ]);

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