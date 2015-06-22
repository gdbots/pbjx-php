<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\DomainCommand;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface CommandHandler
{
    /**
     * @param DomainCommand $command
     * @param Pbjx $pbjx
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    public function handleCommand(DomainCommand $command, Pbjx $pbjx);
}
