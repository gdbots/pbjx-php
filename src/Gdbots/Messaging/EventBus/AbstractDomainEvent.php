<?php

namespace Gdbots\Messaging\EventBus;

use Gdbots\Messaging\AbstractMessage;

abstract class AbstractDomainEvent extends AbstractMessage implements DomainEventInterface
{
}