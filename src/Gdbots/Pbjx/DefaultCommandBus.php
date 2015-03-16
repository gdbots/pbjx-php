<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Mixin\Command;
use Gdbots\Pbjx\Event\CommandBusEvent;
use Gdbots\Pbjx\Event\CommandBusExceptionEvent;
use Gdbots\Pbjx\Exception\InvalidHandler;

class DefaultCommandBus implements CommandBus
{
    /** @var Dispatcher */
    protected $dispatcher;

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
        $this->dispatcher = $this->locator->getDispatcher();
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
        $curie = $command::schema()->getId()->getCurie();
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
                    new CommandBusExceptionEvent($command, $e)
                );
                return;
            }
            $this->handlers[$curieStr] = $handler;
        }

        try {
            $event = new CommandBusEvent($command);
            PbjxEventBroadcaster::broadcast($this->dispatcher, $command, $event, PbjxEvents::COMMAND_BEFORE_HANDLE);
            $handler->handleCommand($command, $this->pbjx);
            PbjxEventBroadcaster::broadcast($this->dispatcher, $command, $event, PbjxEvents::COMMAND_AFTER_HANDLE);
        } catch (\Exception $e) {
            $this->locator->getExceptionHandler()->onCommandBusException(
                new CommandBusExceptionEvent($command, $e)
            );
        }
    }
}
