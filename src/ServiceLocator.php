<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\EventStore\EventStore;
use Gdbots\Pbjx\Scheduler\Scheduler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

interface ServiceLocator
{
    /**
     * @return Pbjx
     */
    public function getPbjx(): Pbjx;

    /**
     * @return EventDispatcherInterface
     */
    public function getDispatcher(): EventDispatcherInterface;

    /**
     * @return CommandBus
     */
    public function getCommandBus(): CommandBus;

    /**
     * @return EventBus
     */
    public function getEventBus(): EventBus;

    /**
     * @return RequestBus
     */
    public function getRequestBus(): RequestBus;

    /**
     * @return ExceptionHandler
     */
    public function getExceptionHandler(): ExceptionHandler;

    /**
     * Returns the handler for the provided command.
     *
     * @param SchemaCurie $curie
     *
     * @return CommandHandler
     * @throws Exception\HandlerNotFound
     */
    public function getCommandHandler(SchemaCurie $curie): CommandHandler;

    /**
     * Returns the handler for the provided request.
     *
     * @param SchemaCurie $curie
     *
     * @return RequestHandler
     * @throws Exception\HandlerNotFound
     */
    public function getRequestHandler(SchemaCurie $curie): RequestHandler;

    /**
     * @return EventStore
     */
    public function getEventStore(): EventStore;

    /**
     * @return EventSearch
     */
    public function getEventSearch(): EventSearch;

    /**
     * @return Scheduler
     */
    public function getScheduler(): Scheduler;
}
