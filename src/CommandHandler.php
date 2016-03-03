<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Schemas\Pbjx\Command\Command;

interface CommandHandler
{
    /**
     * @param Command $command
     * @param Pbjx $pbjx
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleCommand(Command $command, Pbjx $pbjx);
}
