<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\Transport\Transport;

final class SimpleCommandBus implements CommandBus
{
    private ServiceLocator $locator;
    private Transport $transport;

    public function __construct(ServiceLocator $locator, Transport $transport)
    {
        $this->locator = $locator;
        $this->transport = $transport;
    }

    public function send(Message $command): void
    {
        $this->transport->sendCommand($command->freeze());
    }

    /**
     * Invokes the handler that services the given command.  If an exception occurs
     * it will be processed by the exception handler and not thrown by this method.
     *
     * It is up to the handler or exception handler to retry the command (if appropriate)
     * or message the user in some way that their command has failed.  This process is
     * expected to be running asynchronously and very likely in a background process so
     * allowing an exception to just bubble up and break the service handling commands
     * will not be seen by anyone except an error log.
     *
     * {@inheritdoc}
     */
    public function receiveCommand(Message $command): void
    {
        try {
            $command->freeze();
            $handler = $this->locator->getCommandHandler($command::schema()->getCurie());
            $pbjx = $this->locator->getPbjx();
            $event = new PbjxEvent($command);
            $pbjx->trigger($command, PbjxEvents::SUFFIX_BEFORE_HANDLE, $event, false);
            $handler->handleCommand($command, $pbjx);
            $pbjx->trigger($command, PbjxEvents::SUFFIX_AFTER_HANDLE, $event, false);
        } catch (\Throwable $e) {
            $this->locator->getExceptionHandler()->onCommandBusException(new BusExceptionEvent($command, $e));
        }
    }
}
