<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Transport;

use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;

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
     * @param string $prefix
     */
    public function __construct(?string $prefix = null)
    {
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function forCommand(Command $command): string
    {
        return $this->prefix . static::DEFAULT_COMMAND_CHANNEL;
    }

    /**
     * {@inheritdoc}
     */
    public function forEvent(Event $event): string
    {
        return $this->prefix . static::DEFAULT_EVENT_CHANNEL;
    }

    /**
     * {@inheritdoc}
     */
    public function forRequest(Request $request): string
    {
        return $this->prefix . static::DEFAULT_REQUEST_CHANNEL;
    }
}
