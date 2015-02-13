<?php

namespace Gdbots\PbjxBack\EventBus;

use Gdbots\PbjxBack\AbstractMessage;

abstract class AbstractDomainEvent extends AbstractMessage implements DomainEventInterface
{
}