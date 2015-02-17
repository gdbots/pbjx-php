<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface CommandHandler
{
    /**
     * @param Command $command
     * @param Pbjx $pbjx
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleCommand(Command $command, Pbjx $pbjx);
}
