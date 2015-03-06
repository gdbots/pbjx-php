<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\Extension\AbstractRequest;
use Gdbots\Pbj\Extension\RequestSchema;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class GetTimeRequest extends AbstractRequest
{
    const TEST_FAIL_FIELD_NAME = 'test_fail';

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = RequestSchema::create(__CLASS__, 'pbj:gdbots:tests.pbjx:fixtures:get-time-request:1-0-0', [
            Fb::create(self::TEST_FAIL_FIELD_NAME, T\BooleanType::create())->build(),
        ]);
        MessageResolver::registerSchema($schema);
        return $schema;
    }

    /**
     * @return bool
     */
    public function getTestFail()
    {
        return $this->get(self::TEST_FAIL_FIELD_NAME);
    }

    /**
     * @param bool $testFail
     * @return self
     */
    public function setTestFail($testFail)
    {
        return $this->setSingleValue(self::TEST_FAIL_FIELD_NAME, $testFail);
    }
}