<?php

namespace Gdbots\Pbjx\Domain\Event;

use Gdbots\Pbj\Mixin\AbstractEvent;
use Gdbots\Pbj\Mixin\DomainEvent;
use Gdbots\Pbj\Mixin\EventMixin;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class EventExecutionFailedV1 extends AbstractEvent
{
    const FAILED_EVENT_FIELD_NAME = 'failed_event';
    const REASON_FIELD_NAME = 'reason';

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        return new Schema('pbj:gdbots:pbjx:event:event-execution-failed:1-0-0', __CLASS__,
            [
                Fb::create(self::FAILED_EVENT_FIELD_NAME, T\MessageType::create())
                    ->required()
                    ->anyOfClassNames(['Gdbots\Pbj\Mixin\DomainEvent'])
                    ->build(),
                Fb::create(self::REASON_FIELD_NAME, T\TextType::create())
                    ->build(),
            ],
            [
                EventMixin::create()
            ]
        );
    }

    /**
     * @return DomainEvent
     */
    public function getFailedEvent()
    {
        return $this->get(self::FAILED_EVENT_FIELD_NAME);
    }

    /**
     * @param DomainEvent $domainEvent
     * @return self
     */
    public function setFailedEvent(DomainEvent $domainEvent)
    {
        return $this->setSingleValue(self::FAILED_EVENT_FIELD_NAME, $domainEvent);
    }

    /**
     * @return string
     */
    public function getReason()
    {
        return $this->get(self::REASON_FIELD_NAME);
    }

    /**
     * @param string $reason
     * @return self
     */
    public function setReason($reason)
    {
        return $this->setSingleValue(self::REASON_FIELD_NAME, $reason);
    }
}
