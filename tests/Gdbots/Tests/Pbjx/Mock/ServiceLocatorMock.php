<?php

namespace Gdbots\Tests\Pbjx\Mock;

use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbj\MessageCurie;
use Gdbots\Pbjx\CommandBus;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\DefaultCommandBus;
use Gdbots\Pbjx\DefaultEventBus;
use Gdbots\Pbjx\DefaultExceptionHandler;
use Gdbots\Pbjx\DefaultPbjx;
use Gdbots\Pbjx\DefaultRequestBus;
use Gdbots\Pbjx\Dispatcher;
use Gdbots\Pbjx\Exception\HandlerNotFound;
use Gdbots\Pbjx\ExceptionHandler;
use Gdbots\Pbjx\EventBus;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestBus;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Pbjx\Transport;

class ServiceLocatorMock implements ServiceLocator
{
    /** @var Pbjx */
    private $pbjx;

    /** @var Dispatcher */
    private $dispatcher;

    /** @var Transport */
    private $transport;

    /** @var CommandBus */
    private $commandBus;

    /** @var EventBus */
    private $eventBus;

    /** @var RequestBus */
    private $requestBus;

    /** @var ExceptionHandler */
    private $exceptionHandler;

    private $handlers = [];

    public function __construct()
    {
        $this->dispatcher = new DispatcherMock();
        $this->transport = new Transport\InMemoryTransport($this);
        $this->pbjx = new DefaultPbjx($this);

        $this->commandBus = new DefaultCommandBus($this, $this->transport);
        $this->eventBus = new DefaultEventBus($this, $this->transport);
        $this->requestBus = new DefaultRequestBus($this, $this->transport);
        $this->exceptionHandler = new DefaultExceptionHandler($this);

        /*
        $this->dispatcher->addListener(
            'gdbots:*',
            function (DomainEvent $event) {
                echo $event . PHP_EOL . PHP_EOL;
            }
        );
        */
    }

    /**
     * @param MessageCurie $curie
     * @param CommandHandler $handler
     */
    public function registerCommandHandler(MessageCurie $curie, CommandHandler $handler)
    {
        $this->handlers[$curie->toString()] = $handler;
    }

    /**
     * @param MessageCurie $curie
     * @param RequestHandler $handler
     */
    public function registerRequestHandler(MessageCurie $curie, RequestHandler $handler)
    {
        $this->handlers[$curie->toString()] = $handler;
    }

    /**
     * {@inheritdoc}
     */
    public function getPbjx()
    {
        return $this->pbjx;
    }

    /**
     * {@inheritdoc}
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandBus()
    {
        return $this->commandBus;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventBus()
    {
        return $this->eventBus;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestBus()
    {
        return $this->requestBus;
    }

    /**
     * {@inheritdoc}
     */
    public function getExceptionHandler()
    {
        return $this->exceptionHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandHandler(MessageCurie $curie)
    {
        if (isset($this->handlers[$curie->toString()])) {
            return $this->handlers[$curie->toString()];
        }
        throw new HandlerNotFound($curie);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestHandler(MessageCurie $curie)
    {
        if (isset($this->handlers[$curie->toString()])) {
            return $this->handlers[$curie->toString()];
        }
        throw new HandlerNotFound($curie);
    }
}
