<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Event\CommandBusExceptionEvent;
use Psr\Log\LoggerInterface;

class DefaultExceptionHandler implements ExceptionHandler
{
    /** @var Dispatcher */
    protected $dispatcher;

    /** @var ServiceLocator */
    protected $locator;

    /** @var LoggerInterface */
    protected $logger;

    /** @var Pbjx */
    protected $pbjx;

    /**
     * @param Dispatcher $dispatcher
     * @param ServiceLocator $locator
     * @param LoggerInterface $logger
     */
    public function __construct(Dispatcher $dispatcher, ServiceLocator $locator, LoggerInterface $logger)
    {
        $this->dispatcher = $dispatcher;
        $this->locator = $locator;
        $this->logger = $logger;
        $this->pbjx = $this->locator->getPbjx();
    }

    /**
     * {@inheritdoc}
     */
    public function onCommandBusException(CommandBusExceptionEvent $event)
    {
        $command = $event->getCommand();
        $curie = $command::schema()->getId()->getCurie();
        $curieStr = $curie->toString();

        //$this->logger->error('')

        $this->dispatcher->dispatch(PbjxEvents::COMMAND_HANDLE_EXCEPTION, $event);
        $this->dispatcher->dispatch($curieStr . '.exception', $event);
    }
}
