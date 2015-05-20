<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Mixin\RequestMixin;
use Gdbots\Pbj\Mixin\RequestTrait;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class GetTimeRequest extends AbstractMessage implements DomainRequest
{
    use RequestTrait;

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:get-time-request:1-0-0', __CLASS__,
            [
                Fb::create('test_fail', T\BooleanType::create())->build(),
            ],
            [
                RequestMixin::create()
            ]
        );
        MessageResolver::registerSchema($schema);
        return $schema;
    }

    /**
     * @return bool
     */
    public function getTestFail()
    {
        return $this->get('test_fail');
    }

    /**
     * @param bool $testFail
     * @return self
     */
    public function setTestFail($testFail)
    {
        return $this->setSingleValue('test_fail', $testFail);
    }
}