<?php

namespace Gdbots\PbjxBack\CommandBus;

interface CommandBusInterface
{
    /**
     * Processes a command asynchronously.
     *
     * @param CommandInterface $command
     * @return void
     *
     * @throws \Exception
     */
    public function send(CommandInterface $command);

    /**
     * Processes a command directly.  DO NOT use this method in
     * the application as this is intended for the consumers
     * and workers of the messaging system.
     *
     * @param CommandInterface $command
     * @return void
     *
     * @throws \Exception
     */
    public function receiveCommand(CommandInterface $command);
}