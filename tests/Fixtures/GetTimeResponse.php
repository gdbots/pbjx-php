<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Mixin\ResponseMixin;
use Gdbots\Pbj\Mixin\ResponseTrait;
use Gdbots\Pbj\Response;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class GetTimeResponse extends AbstractMessage implements Response
{
    use ResponseTrait;

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        $schema = new Schema('pbj:gdbots:tests.pbjx:fixtures:get-time-response:1-0-0', __CLASS__,
            [
                Fb::create('time', T\DateTimeType::create())->build()
            ],
            [
                ResponseMixin::create()
            ]
        );
        MessageResolver::registerSchema($schema);
        return $schema;
    }

    /**
     * @return \DateTime
     */
    public function getTime()
    {
        return $this->get('time');
    }

    /**
     * @param \DateTime $time
     * @return self
     */
    public function setTime(\DateTime $time)
    {
        return $this->setSingleValue('time', $time);
    }
}