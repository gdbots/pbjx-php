<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbj\SchemaId;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;

interface ServiceLocator
{
    /**
     * @return CommandBus
     */
    public function getCommandBus();

    /**
     * @return EventBus
     */
    public function getEventBus();

    /**
     * Returns the handler for the provided schema.
     *
     * There can be only one handler for a given command and it must have a
     * "camelizedCommandName" method that accepts the class returned
     * from the @see SchemaId::getClassName method.
     *
     * @param SchemaId $schemaId
     * @return CommandHandler
     * @throws Exception\HandlerNotFound
     * @throws GdbotsPbjxException
     */
    public function getCommandHandler(SchemaId $schemaId);
}
