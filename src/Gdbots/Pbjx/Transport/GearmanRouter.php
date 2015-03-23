<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Mixin\Command;
use Gdbots\Pbj\Mixin\DomainEvent;
use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbjx\Router;

class GearmanRouter implements Router
{
    /**
     * {@inheritdoc}
     */
    public function forCommand(Command $command)
    {
        return 'commands';
    }

    /**
     * {@inheritdoc}
     */
    public function forEvent(DomainEvent $domainEvent)
    {
        return 'events';
    }

    /**
     * {@inheritdoc}
     */
    public function forRequest(Request $request)
    {
        return 'requests';
    }
}
