<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Command;
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
     * @param Dispatcher $dispatcher
     * @param ServiceLocator $locator
     * @param Transport $transport
     */
    public function __construct(Dispatcher $dispatcher, ServiceLocator $locator, Transport $transport)
    {
        $this->dispatcher = $dispatcher;
        $this->locator = $locator;
        $this->transport = $transport;
        $this->pbjx = $this->locator->getPbjx();
    }

    /**
     * {@inheritdoc}
     */
    public function send(Command $command)
    {
        $this->transport->sendCommand($command);
    }

    /**
     * {@inheritdoc}
     */
    public function receiveCommand(Command $command)
    {
        $this->handleCommand($command);
    }

    /**
     * Invokes the handler that services the given command.
     *
     * @param Command $command
     * @throws \Exception
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
                        $command,
                        sprintf('The class "%s" must implement CommandHandler.', get_class($handler))
                    );
                }
            } catch (\Exception $e) {
                $this->locator->getExceptionHandler()->onCommandBusException(new CommandBusExceptionEvent($command, $e));
                return;
            }
            $this->handlers[$curieStr] = $handler;
        }

        try {
            $event = new CommandBusEvent($command);
            $this->dispatcher->dispatch(PbjxEvents::COMMAND_BEFORE_HANDLE, $event);
            $this->dispatcher->dispatch($curieStr . '.before_handle', $event);

            $handler->handleCommand($command, $this->pbjx);

            $this->dispatcher->dispatch(PbjxEvents::COMMAND_AFTER_HANDLE, $event);
            $this->dispatcher->dispatch($curieStr . '.after_handle', $event);
        } catch (\Exception $e) {
            $this->locator->getExceptionHandler()->onCommandBusException(new CommandBusExceptionEvent($command, $e));
            return;
        }
    }
}
