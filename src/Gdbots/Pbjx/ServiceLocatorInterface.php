<?php

namespace Gdbots\Pbjx;

interface ServiceLocatorInterface
{
    /**
     * @return CommandBus\CommandBusInterface
     */
    public function getCommandBus();

    /**
     * @return EventBus\EventBusInterface
     */
    public function getEventBus();

    /**
     * @return RequestBus\RequestBusInterface
     */
    public function getRequestBus();

    /**
     * Returns the handler for the provided message.
     *
     * There can be only one handler for a given command and it must have a
     * "camelizedCommandName" method that accepts the class returned
     * from the MessageCurie::getClassName method.
     *
     * @param MessageCurie $curie
     * @return CommandBus\CommandHandlerInterface
     * @throws Exception\HandlerNotFoundException
     */
    public function getCommandHandler(MessageCurie $curie);

    /**
     * Returns the handler for the provided message.
     *
     * There can be only one handler for a given request and it must have a
     * "camelizedCommandName" method that accepts the class returned
     * from the MessageCurie::getClassName method.
     *
     * @param MessageCurie $curie
     * @return RequestBus\RequestHandlerInterface
     * @throws Exception\HandlerNotFoundException
     */
    public function getRequestHandler(MessageCurie $curie);
}
