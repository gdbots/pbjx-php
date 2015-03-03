<?php

namespace Gdbots\Pbjx\Domain\Event;

use Gdbots\Pbj\Extension\AbstractEvent;
use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbj\Extension\EventSchema;
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
        return EventSchema::create(__CLASS__, 'pbj:gdbots:pbjx:event:event-execution-failed:1-0-0', [
            Fb::create(self::FAILED_EVENT_FIELD_NAME, T\AnyMessageType::create())
                ->required()
                ->build(),
            Fb::create(self::REASON_FIELD_NAME, T\TextType::create())
                ->build(),
        ]);
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
