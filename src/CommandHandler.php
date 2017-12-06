<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\DependencyInjection\PbjxHandler;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;

interface CommandHandler extends PbjxHandler
{
    /**
     * @param Command $command
     * @param Pbjx    $pbjx
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleCommand(Command $command, Pbjx $pbjx): void;
}
