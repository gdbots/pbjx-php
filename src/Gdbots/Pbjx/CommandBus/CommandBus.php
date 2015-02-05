<?php

namespace Gdbots\Pbjx\CommandBus;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Gdbots\Common\Util\StringUtils;
use Gdbots\Pbjx\Exception;
use Gdbots\Pbjx\LifecycleEvent\CommandBus\CommandBusEvent;
use Gdbots\Pbjx\LifecycleEvent\CommandBus\CommandBusEvents;
use Gdbots\Pbjx\LifecycleEvent\CommandBus\CommandBusExceptionEvent;
use Gdbots\Pbjx\LifecycleEvent\CommandBus\EnrichCommandEvent;
use Gdbots\Pbjx\LifecycleEvent\CommandBus\ValidateCommandEvent;
use Gdbots\Pbjx\ServiceLocatorInterface;
use Gdbots\Pbjx\TransportInterface;

class CommandBus implements CommandBusInterface
{
    /* @var EventDispatcherInterface */
    protected $dispatcher;

    /* @var CommandHandlerInterface[] */
    private $handlers = array();

    /* @var ServiceLocatorInterface */
    protected $locator;

    /* @var TransportInterface */
    protected $transport;

    /**
     * @param EventDispatcherInterface $dispatcher
     * @param ServiceLocatorInterface $locator
     * @param TransportInterface $transport
     */
    public function __construct(EventDispatcherInterface $dispatcher, ServiceLocatorInterface $locator, TransportInterface $transport)
    {
        $this->dispatcher = $dispatcher;
        $this->locator = $locator;
        $this->transport = $transport;
    }

    /**
     * @see CommandBusInterface::send
     */
    public function send(CommandInterface $command)
    {
        $curie = $command->meta()->getCurie();

        $event = new ValidateCommandEvent($command);
        $this->dispatcher->dispatch(CommandBusEvents::VALIDATE_COMMAND, $event);
        $this->dispatcher->dispatch($curie . '.validate', $event);

        $event = new EnrichCommandEvent($command);
        $this->dispatcher->dispatch(CommandBusEvents::ENRICH_COMMAND, $event);
        $this->dispatcher->dispatch($curie . '.enrich', $event);

        $this->doSend($command);
    }

    /**
     * @param CommandInterface $command
     */
    protected function doSend(CommandInterface $command)
    {
        $this->transport->sendCommand($command);
    }

    /**
     * @see CommandBusInterface::receiveCommand
     */
    public function receiveCommand(CommandInterface $command)
    {
        $this->handleCommand($command);
    }

    /**
     * Invokes the handler that services the given command.
     *
     * @param CommandInterface $command
     * @throws \Exception
     */
    protected final function handleCommand(CommandInterface $command)
    {
        $curie = $command->meta()->getCurie();
        $method = lcfirst(StringUtils::toCamelCaseFromSlug($curie->getMessage()));

        if (isset($this->handlers[$curie->toString()])) {
            $handler = $this->handlers[$curie->toString()];
        } else {
            $handler = $this->locator->getCommandHandler($curie);
            $commandClass = $curie::getClassName($curie->toString());

            if (!$handler instanceof CommandHandlerInterface) {
                $exception = new Exception\UnexpectedValueException(
                        sprintf('The class "%s" handler must implement CommandHandlerInterface.', get_class($handler)));

                $exceptionEvent = new CommandBusExceptionEvent($command, $exception);
                $this->dispatcher->dispatch(CommandBusEvents::EXCEPTION, $exceptionEvent);
                throw $exception;
            }

            if (!$this->canInvokeHandler($handler, $method, $commandClass)) {
                $exception = new Exception\BadMethodCallException(
                        sprintf(
                            'The "%s::%s" method must accept "%s" as the first ' .
                            'argument and any other arguments must be optional.',
                            get_class($handler), $method, $commandClass
                        ));

                $exceptionEvent = new CommandBusExceptionEvent($command, $exception);
                $this->dispatcher->dispatch(CommandBusEvents::EXCEPTION, $exceptionEvent);
                throw $exception;
            }

            $this->handlers[$curie->toString()] = $handler;
        }

        $event = new CommandBusEvent($command);
        $this->dispatcher->dispatch(CommandBusEvents::BEFORE_HANDLE_COMMAND, $event);
        $this->dispatcher->dispatch($curie . '.before_handle', $event);

        try {
            $handler->$method($command);
        } catch (\Exception $e) {
            $exceptionEvent = new CommandBusExceptionEvent($command, $e);
            $this->dispatcher->dispatch(CommandBusEvents::EXCEPTION, $exceptionEvent);
            throw $e;
        }

        $this->dispatcher->dispatch(CommandBusEvents::AFTER_HANDLE_COMMAND, $event);
        $this->dispatcher->dispatch($curie . '.after_handle', $event);
    }

    /**
     * Returns true if the handler's method accepts the required class name
     * we plan to provide and has no other required arguments.
     *
     * @param mixed $handler
     * @param string $method
     * @param string $requiredClassName
     *
     * @return boolean
     */
    private function canInvokeHandler($handler, $method, $requiredClassName)
    {
        try {
            $r = new \ReflectionMethod($handler, $method);
            $parameters = $r->getParameters();
        } catch (\Exception $e) {
            return false;
        }

        if (count($parameters) === 0) {
            return false;
        }

        $i = 0;
        foreach ($parameters as $param) {
            ++$i;

            if ($i === 1) {
                $paramClass = $param->getClass();
                if ($requiredClassName !== $paramClass->getName()) {
                    return false;
                }
                continue;
            }

            if (!$param->isOptional()) {
                return false;
            }
        }

        return true;
    }
}
