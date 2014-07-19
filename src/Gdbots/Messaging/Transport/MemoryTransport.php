<?php

namespace Gdbots\Messaging\Transport;

use Gdbots\Messaging\CommandBus\CommandInterface;

class MemoryTransport extends AbstractTransport
{
    /**
     * @see TransportInterface::getName
     */
    public static function getName()
    {
        return 'memory';
    }

    /**
     * Processes the command in memory synchronously.
     *
     * @param CommandInterface $command
     * @throws \Exception
     */
    protected function doSendCommand(CommandInterface $command)
    {
        $this->locator->getCommandBus()->receiveCommand($command);
    }
}
