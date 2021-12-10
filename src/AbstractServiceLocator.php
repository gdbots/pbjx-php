<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\EventStore\EventStore;
use Gdbots\Pbjx\Exception\LogicException;
use Gdbots\Pbjx\Scheduler\Scheduler;
use Gdbots\Pbjx\Transport\InMemoryTransport;
use Gdbots\Pbjx\Transport\Transport;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class AbstractServiceLocator implements ServiceLocator
{
    protected ?EventStore $eventStore = null;
    protected ?EventSearch $eventSearch = null;
    protected ?Scheduler $scheduler = null;
    protected ?Transport $defaultTransport = null;

    private ?Pbjx $pbjx = null;
    private ?EventDispatcherInterface $dispatcher = null;
    private ?CommandBus $commandBus = null;
    private ?EventBus $eventBus = null;
    private ?RequestBus $requestBus = null;
    private ?ExceptionHandler $exceptionHandler = null;

    final public function getPbjx(): Pbjx
    {
        if (null === $this->pbjx) {
            $this->pbjx = $this->doGetPbjx();
        }

        return $this->pbjx;
    }

    protected function doGetPbjx(): Pbjx
    {
        return new SimplePbjx($this);
    }

    final public function getDispatcher(): EventDispatcherInterface
    {
        if (null === $this->dispatcher) {
            $this->dispatcher = $this->doGetDispatcher();
        }

        return $this->dispatcher;
    }

    protected function doGetDispatcher(): EventDispatcherInterface
    {
        return new EventDispatcher();
    }

    final public function getCommandBus(): CommandBus
    {
        if (null === $this->commandBus) {
            $this->commandBus = $this->doGetCommandBus();
        }

        return $this->commandBus;
    }

    protected function doGetCommandBus(): CommandBus
    {
        return new SimpleCommandBus($this, $this->getDefaultTransport());
    }

    final public function getEventBus(): EventBus
    {
        if (null === $this->eventBus) {
            $this->eventBus = $this->doGetEventBus();
        }

        return $this->eventBus;
    }

    protected function doGetEventBus(): EventBus
    {
        return new SimpleEventBus($this, $this->getDefaultTransport());
    }

    final public function getRequestBus(): RequestBus
    {
        if (null === $this->requestBus) {
            $this->requestBus = $this->doGetRequestBus();
        }

        return $this->requestBus;
    }

    protected function doGetRequestBus(): RequestBus
    {
        return new SimpleRequestBus($this, $this->getDefaultTransport());
    }

    final public function getExceptionHandler(): ExceptionHandler
    {
        if (null === $this->exceptionHandler) {
            $this->exceptionHandler = $this->doGetExceptionHandler();
        }

        return $this->exceptionHandler;
    }

    protected function doGetExceptionHandler(): ExceptionHandler
    {
        return new LogAndDispatchExceptionHandler($this);
    }

    final public function getEventStore(): EventStore
    {
        if (null === $this->eventStore) {
            $this->eventStore = $this->doGetEventStore();
        }

        return $this->eventStore;
    }

    protected function doGetEventStore(): EventStore
    {
        throw new LogicException('No EventStore has been configured.', Code::UNIMPLEMENTED->value);
    }

    final public function getEventSearch(): EventSearch
    {
        if (null === $this->eventSearch) {
            $this->eventSearch = $this->doGetEventSearch();
        }

        return $this->eventSearch;
    }

    protected function doGetEventSearch(): EventSearch
    {
        throw new LogicException('No EventSearch has been configured.', Code::UNIMPLEMENTED->value);
    }

    final public function getScheduler(): Scheduler
    {
        if (null === $this->scheduler) {
            $this->scheduler = $this->doGetScheduler();
        }

        return $this->scheduler;
    }

    protected function doGetScheduler(): Scheduler
    {
        throw new LogicException('No Scheduler has been configured.', Code::UNIMPLEMENTED->value);
    }

    final protected function getDefaultTransport(): Transport
    {
        if (null === $this->defaultTransport) {
            $this->defaultTransport = $this->doGetDefaultTransport();
        }

        return $this->defaultTransport;
    }

    protected function doGetDefaultTransport(): Transport
    {
        return new InMemoryTransport($this);
    }
}
