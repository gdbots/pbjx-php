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
    /** @var EventStore */
    protected $eventStore;

    /** @var EventSearch */
    protected $eventSearch;

    /** @var Scheduler */
    protected $scheduler;

    /** @var Transport */
    protected $defaultTransport;

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

    /**
     * {@inheritdoc}
     */
    final public function getPbjx(): Pbjx
    {
        if (null === $this->pbjx) {
            $this->pbjx = $this->doGetPbjx();
        }

        return $this->pbjx;
    }

    /**
     * @return Pbjx
     */
    protected function doGetPbjx(): Pbjx
    {
        return new SimplePbjx($this);
    }

    /**
     * {@inheritdoc}
     */
    final public function getDispatcher(): EventDispatcherInterface
    {
        if (null === $this->dispatcher) {
            $this->dispatcher = $this->doGetDispatcher();
        }

        return $this->dispatcher;
    }

    /**
     * @return EventDispatcherInterface
     */
    protected function doGetDispatcher(): EventDispatcherInterface
    {
        return new EventDispatcher();
    }

    /**
     * {@inheritdoc}
     */
    final public function getCommandBus(): CommandBus
    {
        if (null === $this->commandBus) {
            $this->commandBus = $this->doGetCommandBus();
        }

        return $this->commandBus;
    }

    /**
     * @return CommandBus
     */
    protected function doGetCommandBus(): CommandBus
    {
        return new SimpleCommandBus($this, $this->getDefaultTransport());
    }

    /**
     * {@inheritdoc}
     */
    final public function getEventBus(): EventBus
    {
        if (null === $this->eventBus) {
            $this->eventBus = $this->doGetEventBus();
        }

        return $this->eventBus;
    }

    /**
     * @return EventBus
     */
    protected function doGetEventBus(): EventBus
    {
        return new SimpleEventBus($this, $this->getDefaultTransport());
    }

    /**
     * {@inheritdoc}
     */
    final public function getRequestBus(): RequestBus
    {
        if (null === $this->requestBus) {
            $this->requestBus = $this->doGetRequestBus();
        }

        return $this->requestBus;
    }

    /**
     * @return RequestBus
     */
    protected function doGetRequestBus(): RequestBus
    {
        return new SimpleRequestBus($this, $this->getDefaultTransport());
    }

    /**
     * {@inheritdoc}
     */
    final public function getExceptionHandler(): ExceptionHandler
    {
        if (null === $this->exceptionHandler) {
            $this->exceptionHandler = $this->doGetExceptionHandler();
        }

        return $this->exceptionHandler;
    }

    /**
     * @return ExceptionHandler
     */
    protected function doGetExceptionHandler(): ExceptionHandler
    {
        return new LogAndDispatchExceptionHandler($this);
    }

    /**
     * @return EventStore
     */
    final public function getEventStore(): EventStore
    {
        if (null === $this->eventStore) {
            $this->eventStore = $this->doGetEventStore();
        }

        return $this->eventStore;
    }

    /**
     * @return EventStore
     */
    protected function doGetEventStore(): EventStore
    {
        throw new LogicException('No EventStore has been configured.', Code::UNIMPLEMENTED);
    }

    /**
     * @return EventSearch
     */
    final public function getEventSearch(): EventSearch
    {
        if (null === $this->eventSearch) {
            $this->eventSearch = $this->doGetEventSearch();
        }

        return $this->eventSearch;
    }

    /**
     * @return EventSearch
     */
    protected function doGetEventSearch(): EventSearch
    {
        throw new LogicException('No EventSearch has been configured.', Code::UNIMPLEMENTED);
    }

    /**
     * @return Scheduler
     */
    final public function getScheduler(): Scheduler
    {
        if (null === $this->scheduler) {
            $this->scheduler = $this->doGetScheduler();
        }

        return $this->scheduler;
    }

    /**
     * @return Scheduler
     */
    protected function doGetScheduler(): Scheduler
    {
        throw new LogicException('No Scheduler has been configured.', Code::UNIMPLEMENTED);
    }

    /**
     * @return Transport
     */
    final protected function getDefaultTransport(): Transport
    {
        if (null === $this->defaultTransport) {
            $this->defaultTransport = $this->doGetDefaultTransport();
        }

        return $this->defaultTransport;
    }

    /**
     * @return Transport
     */
    protected function doGetDefaultTransport(): Transport
    {
        return new InMemoryTransport($this);
    }
}
