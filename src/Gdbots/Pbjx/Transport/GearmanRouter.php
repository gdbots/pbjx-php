<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Mixin\Command;
use Gdbots\Pbj\Mixin\DomainEvent;
use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbjx\Router;

class GearmanRouter implements Router
{
    /**
     * Prefixes the channel the work will be routed to.  This is useful
     * for routing all messages to appname_env_pbjx_commands|events|request
     * when you're running multiple environments or apps on a single gearmand.
     *
     * @var string
     */
    protected $prefix;

    /**
     * @param string|null $prefix
     */
    public function __construct($prefix = null)
    {
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function forCommand(Command $command)
    {
        return $this->prefix . Router::DEFAULT_COMMAND_CHANNEL;
    }

    /**
     * {@inheritdoc}
     */
    public function forEvent(DomainEvent $domainEvent)
    {
        return $this->prefix . Router::DEFAULT_EVENT_CHANNEL;
    }

    /**
     * {@inheritdoc}
     */
    public function forRequest(Request $request)
    {
        return $this->prefix . Router::DEFAULT_REQUEST_CHANNEL;
    }
}

