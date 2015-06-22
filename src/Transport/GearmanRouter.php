<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\DomainCommand;
use Gdbots\Pbj\DomainEvent;
use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbjx\Router;

class GearmanRouter implements Router
{
    const DEFAULT_COMMAND_CHANNEL = 'pbjx_commands';
    const DEFAULT_EVENT_CHANNEL = 'pbjx_events';
    const DEFAULT_REQUEST_CHANNEL = 'pbjx_requests';

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
    public function forCommand(DomainCommand $command)
    {
        return $this->prefix . static::DEFAULT_COMMAND_CHANNEL;
    }

    /**
     * {@inheritdoc}
     */
    public function forEvent(DomainEvent $domainEvent)
    {
        return $this->prefix . static::DEFAULT_EVENT_CHANNEL;
    }

    /**
     * {@inheritdoc}
     */
    public function forRequest(DomainRequest $request)
    {
        return $this->prefix . static::DEFAULT_REQUEST_CHANNEL;
    }
}

