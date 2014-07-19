<?php

namespace Gdbots\Messaging\LifecycleEvent\CommandBus;

final class CommandBusEvents
{
    /**
     * Private constructor. This class is not meant to be instantiated.
     */
    private function __construct() {}

    /**
     * Occurs prior to command being sent to the transport.
     * Event listener will receive Gdbots\Messaging\LifecycleEvent\CommandBus\ValidateCommandEvent
     *
     * @var string
     */
    const VALIDATE_COMMAND = 'tdn.messaging.command_bus.command.validate';

    /**
     * Occurs after validation and prior to command being sent to the transport.
     * Event listener will receive Gdbots\Messaging\LifecycleEvent\CommandBus\EnrichCommandEvent
     *
     * @var string
     */
    const ENRICH_COMMAND = 'tdn.messaging.command_bus.command.enrich';

    /**
     * Occurs before command is sent to the handler.
     * Event listener will receive Gdbots\Messaging\LifecycleEvent\CommandBus\CommandBusEvent
     *
     * @var string
     */
    const BEFORE_HANDLE_COMMAND = 'tdn.messaging.command_bus.command.before_handle';

    /**
     * Occurs after command has been successfully sent to the handler.
     * Event listener will receive Gdbots\Messaging\LifecycleEvent\CommandBus\CommandBusEvent
     *
     * @var string
     */
    const AFTER_HANDLE_COMMAND = 'tdn.messaging.command_bus.command.after_handle';

    /**
     * Occurs prior to an expection being thrown during the handling phase of a command.  This
     * is not announced during validate, enrich or the transport send.
     *
     * The exception will still be thrown after this event is dispatched.
     *
     * Event listener will receive Gdbots\Messaging\LifecycleEvent\CommandBus\CommandBusExceptionEvent
     *
     * @var string
     */
    const EXCEPTION = 'tdn.messaging.command_bus.exception';
}
