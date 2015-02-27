<?php

namespace Gdbots\Pbjx;

final class PbjxEvents
{
    /**
     * Private constructor. This class is not meant to be instantiated.
     */
    private function __construct() {}

    /**
     * Occurs prior to command being sent to the transport.
     *
     * @see Gdbots\Pbjx\Event\ValidateCommandEvent
     * @var string
     */
    const COMMAND_VALIDATE = 'gdbots.pbjx.command.validate';

    /**
     * Occurs after validation and prior to command being sent to the transport.
     *
     * @see Gdbots\Pbjx\Event\EnrichCommandEvent
     * @var string
     */
    const COMMAND_ENRICH = 'gdbots.pbjx.command.enrich';

    /**
     * Occurs before command is sent to the handler.
     *
     * @see Gdbots\Pbjx\Event\CommandBusEvent
     * @var string
     */
    const COMMAND_BEFORE_HANDLE = 'gdbots.pbjx.command.before_handle';

    /**
     * Occurs after command has been successfully sent to the handler.
     *
     * @see Gdbots\Pbjx\Event\CommandBusEvent
     * @var string
     */
    const COMMAND_AFTER_HANDLE = 'gdbots.pbjx.command.after_handle';

    /**
     * Occurs prior to an expection being thrown during the handling phase of a command.
     * This is not announced during validate, enrich or the transport send.
     *
     * @see Gdbots\Pbjx\Event\CommandBusExceptionEvent
     * @var string
     */
    const COMMAND_HANDLE_EXCEPTION = 'gdbots.pbjx.command.handle_exception';



    /**
     * Occurs prior to an event being sent to the transport.
     *
     * @see Gdbots\Pbjx\Event\EnrichDomainEventEvent
     * @var string
     */
    const EVENT_ENRICH = 'gdbots.pbjx.event.enrich';

    /**
     * Occurs prior to an expection being thrown during the handling phase of a command.
     * This is not announced during validate, enrich or the transport send.
     *
     * @see Gdbots\Pbjx\Event\EventBusExceptionEvent
     * @var string
     */
    const EVENT_SUBSCRIBER_EXCEPTION = 'gdbots.pbjx.event.subscriber_exception';




    /**
     * Occurs prior to the message being sent by the transport.
     *
     * @see Gdbots\Pbjx\Event\TransportEvent
     * @var string
     */
    const TRANSPORT_BEFORE_SEND = 'gdbots.pbjx.transport.before_send';

    /**
     * Occurs after the message has been successfully sent by the transport.
     *
     * @see Gdbots\Pbjx\Event\TransportEvent
     * @var string
     */
    const TRANSPORT_AFTER_SEND = 'gdbots.pbjx.transport.after_send';

    /**
     * Occurs if the transport throws an exception during the send.
     *
     * @see Gdbots\Pbjx\Event\TransportExceptionEvent
     * @var string
     */
    const TRANSPORT_SEND_EXCEPTION = 'gdbots.pbjx.transport.send_exception';
}
