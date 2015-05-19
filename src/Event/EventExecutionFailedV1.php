<?php

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\AbstractMessage;
use Gdbots\Pbj\DomainEvent;
use Gdbots\Pbj\FieldBuilder as Fb;
use Gdbots\Pbj\Mixin\EventMixin;
use Gdbots\Pbj\Mixin\EventTrait;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Type as T;

final class EventExecutionFailedV1 extends AbstractMessage implements DomainEvent
{
    use EventTrait;

    const FAILED_EVENT_FIELD_NAME = 'failed_event';
    const REASON_FIELD_NAME = 'reason';

    /**
     * @return Schema
     */
    protected static function defineSchema()
    {
        return new Schema('pbj:gdbots:pbjx:event:event-execution-failed:1-0-0', __CLASS__,
            [
                Fb::create('failed_event', T\MessageType::create())
                    ->required()
                    ->className('Gdbots\Pbj\DomainEvent')
                    ->build(),
                Fb::create('reason', T\TextType::create())
                    ->build(),
            ],
            [
                EventMixin::create()
            ]
        );
    }

    /**
     * @return bool
     */
    public function hasFailedEvent()
    {
        return $this->has('failed_event');
    }

    /**
     * @return DomainEvent
     */
    public function getFailedEvent()
    {
        return $this->get('failed_event');
    }

    /**
     * @param DomainEvent $domainEvent
     * @return static
     */
    public function setFailedEvent(DomainEvent $domainEvent)
    {
        return $this->setSingleValue('failed_event', $domainEvent);
    }

    /**
     * @return static
     */
    public function clearFailedEvent()
    {
        return $this->clear('failed_event');
    }

    /**
     * @return bool
     */
    public function hasReason()
    {
        return $this->has('reason');
    }

    /**
     * @return string
     */
    public function getReason()
    {
        return $this->get('reason');
    }

    /**
     * @param string $reason
     * @return static
     */
    public function setReason($reason)
    {
        return $this->setSingleValue('reason', $reason);
    }

    /**
     * @return static
     */
    public function clearReason()
    {
        return $this->clear('reason');
    }
}
