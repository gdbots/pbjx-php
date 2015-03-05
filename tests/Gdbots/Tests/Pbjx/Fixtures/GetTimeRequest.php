<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\Extension\AbstractRequest;
use Gdbots\Pbj\Extension\RequestSchema;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class GetTimeRequest extends AbstractRequest
{
    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = RequestSchema::create(__CLASS__, 'pbj:gdbots:tests.pbjx:fixtures:get-time-request:1-0-0');
        MessageResolver::registerSchema($schema);
        return $schema;
    }
}