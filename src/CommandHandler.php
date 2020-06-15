<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\DependencyInjection\PbjxHandler;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface CommandHandler extends PbjxHandler
{
    /**
     * @param Message $command
     * @param Pbjx    $pbjx
     *
     * @throws GdbotsPbjxException
     * @throws \Throwable
     */
    public function handleCommand(Message $command, Pbjx $pbjx): void;
}
