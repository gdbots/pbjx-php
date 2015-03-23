<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Mixin\Command;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\Exception\InvalidHandler;

class DefaultCommandBus implements CommandBus
{
    /** @var ServiceLocator */
    protected $locator;

    /** @var Transport */
    protected $transport;

    /** @var Pbjx */
    protected $pbjx;

    /** @var CommandHandler[] */
    private $handlers = [];

    /**
     * @param ServiceLocator $locator
     * @param Transport $transport
     */
    public function __construct(ServiceLocator $locator, Transport $transport)
    {
        $this->locator = $locator;
        $this->transport = $transport;
        $this->pbjx = $this->locator->getPbjx();
    }

    /**
     * {@inheritdoc}
     */
    public function send(Command $command)
    {
        $this->transport->sendCommand($command->freeze());
    }

    /**
     * {@inheritdoc}
     */
    public function receiveCommand(Command $command)
    {
        $this->handleCommand($command->freeze());
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
     * @param Command $command
     */
    final protected function handleCommand(Command $command)
    {
        $curie = $command::schema()->getCurie();
        $curieStr = $curie->toString();

        if (isset($this->handlers[$curieStr])) {
            $handler = $this->handlers[$curieStr];
        } else {
            try {
                $handler = $this->locator->getCommandHandler($curie);
                if (!$handler instanceof CommandHandler) {
                    throw new InvalidHandler(
                        sprintf('The class "%s" must implement CommandHandler.', get_class($handler))
                    );
                }
            } catch (\Exception $e) {
                $this->locator->getExceptionHandler()->onCommandBusException(
                    new BusExceptionEvent($command, $e)
                );
                return;
            }
            $this->handlers[$curieStr] = $handler;
        }

        try {
            $event = new PbjxEvent($command);
            $this->pbjx->trigger($command, PbjxEvents::SUFFIX_BEFORE_HANDLE, $event);
            $handler->handleCommand($command, $this->pbjx);
            $this->pbjx->trigger($command, PbjxEvents::SUFFIX_AFTER_HANDLE, $event);
        } catch (\Exception $e) {
            $this->locator->getExceptionHandler()->onCommandBusException(
                new BusExceptionEvent($command, $e)
            );
        }
    }
}
