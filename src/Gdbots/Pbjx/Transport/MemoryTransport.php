<?php

namespace Gdbots\Pbjx\Transport;

use Gdbots\Pbj\Extension\Command;

class MemoryTransport extends AbstractTransport
{
    /**
     * Processes the command in memory synchronously.
     *
     * @param Command $command
     * @throws \Exception
     */
    protected function doSendCommand(Command $command)
    {
        $this->locator->getCommandBus()->receiveCommand($command);
    }
}
