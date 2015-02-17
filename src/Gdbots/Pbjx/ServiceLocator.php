<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\MessageCurie;

interface ServiceLocator
{
    /**
     * @return Pbjx
     */
    public function getPbjx();

    /**
     * @return Dispatcher
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
     * @param MessageCurie $curie
     * @return CommandHandler
     * @throws Exception\HandlerNotFound
     */
    public function getCommandHandler(MessageCurie $curie);

    /**
     * Returns the handler for the provided request.
     *
     * @param MessageCurie $curie
     * @return RequestHandler
     * @throws Exception\HandlerNotFound
     */
    public function getRequestHandler(MessageCurie $curie);
}
