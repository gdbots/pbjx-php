<?php

namespace Gdbots\Pbjx;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class AbstractServiceLocator implements ServiceLocator
{
    /** @var Pbjx */
    private $pbjx;

    /** @var EventDispatcherInterface */
    private $dispatcher;

    /** @var CommandBus */
    private $commandBus;

    /** @var EventBus */
    private $eventBus;

    /** @var RequestBus */
    private $requestBus;

    /** @var ExceptionHandler */
    private $exceptionHandler;

    /** @var Transport */
    protected $defaultTransport;

    /**
     * {@inheritdoc}
     */
    final public function getPbjx()
    {
        if (null === $this->pbjx) {
            $this->pbjx = $this->doGetPbjx();
        }
        return $this->pbjx;
    }

    /**
     * @return Pbjx
     */
    protected function doGetPbjx()
    {
        return new DefaultPbjx($this);
    }

    /**
     * {@inheritdoc}
     */
    final public function getDispatcher()
    {
        if (null === $this->dispatcher) {
            $this->dispatcher = $this->doGetDispatcher();
        }
        return $this->dispatcher;
    }

    /**
     * @return EventDispatcherInterface
     */
    protected function doGetDispatcher()
    {
        return new EventDispatcher();
    }

    /**
     * {@inheritdoc}
     */
    final public function getCommandBus()
    {
        if (null === $this->commandBus) {
            $this->commandBus = $this->doGetCommandBus();
        }
        return $this->commandBus;
    }

    /**
     * @return CommandBus
     */
    protected function doGetCommandBus()
    {
        return new DefaultCommandBus($this, $this->getDefaultTransport());
    }

    /**
     * {@inheritdoc}
     */
    final public function getEventBus()
    {
        if (null === $this->eventBus) {
            $this->eventBus = $this->doGetEventBus();
        }
        return $this->eventBus;
    }

    /**
     * @return EventBus
     */
    protected function doGetEventBus()
    {
        return new DefaultEventBus($this, $this->getDefaultTransport());
    }

    /**
     * {@inheritdoc}
     */
    final public function getRequestBus()
    {
        if (null === $this->requestBus) {
            $this->requestBus = $this->doGetRequestBus();
        }
        return $this->requestBus;
    }

    /**
     * @return RequestBus
     */
    protected function doGetRequestBus()
    {
        return new DefaultRequestBus($this, $this->getDefaultTransport());
    }

    /**
     * {@inheritdoc}
     */
    final public function getExceptionHandler()
    {
        if (null === $this->exceptionHandler) {
            $this->exceptionHandler = $this->doGetExceptionHandler();
        }
        return $this->exceptionHandler;
    }

    /**
     * @return ExceptionHandler
     */
    protected function doGetExceptionHandler()
    {
        return new DefaultExceptionHandler($this);
    }

    /**
     * @return Transport
     */
    final protected function getDefaultTransport()
    {
        if (null === $this->defaultTransport) {
            $this->defaultTransport = $this->doGetDefaultTransport();
        }
        return $this->defaultTransport;
    }

    /**
     * @return Transport
     */
    protected function doGetDefaultTransport()
    {
        return new Transport\InMemoryTransport($this);
    }
}
