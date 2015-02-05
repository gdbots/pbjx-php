<?php

namespace Gdbots\Pbjx\EventBus;

use Gdbots\Pbjx\AbstractMessage;

abstract class AbstractDomainEvent extends AbstractMessage implements DomainEventInterface
{
}