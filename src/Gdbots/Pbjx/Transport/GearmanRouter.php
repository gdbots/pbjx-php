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
        return Router::DEFAULT_COMMAND_CHANNEL;
    }

    /**
     * {@inheritdoc}
     */
    public function forEvent(DomainEvent $domainEvent)
    {
        return Router::DEFAULT_EVENT_CHANNEL;
    }

    /**
     * {@inheritdoc}
     */
    public function forRequest(Request $request)
    {
        return Router::DEFAULT_REQUEST_CHANNEL;
    }
}
