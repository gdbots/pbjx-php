<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\EventStore\EventStore;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

interface ServiceLocator
{
    /**
     * @return Pbjx
     */
    public function getPbjx();

    /**
     * @return EventDispatcherInterface
     */
    public function getDispatcher();

    /**
     * @return CommandBus
     */
    public function getCommandBus();

    /**
     * @return EventBus
     */
    public function getEventBus();

    /**
     * @return RequestBus
     */
    public function getRequestBus();

    /**
     * @return ExceptionHandler
     */
    public function getExceptionHandler();

    /**
     * Returns the handler for the provided command.
     *
     * @param SchemaCurie $curie
     * @return CommandHandler
     * @throws Exception\HandlerNotFound
     */
    public function getCommandHandler(SchemaCurie $curie);

    /**
     * Returns the handler for the provided request.
     *
     * @param SchemaCurie $curie
     * @return RequestHandler
     * @throws Exception\HandlerNotFound
     */
    public function getRequestHandler(SchemaCurie $curie);

    /**
     * @return EventStore
     */
    public function getEventStore();

    /**
     * @return EventSearch
     */
    public function getEventSearch();
}
