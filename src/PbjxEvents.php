<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

final class PbjxEvents
{
    /**
     * Private constructor. This class is not meant to be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Suffixes are typically used by @see Pbjx::trigger
     * The actual event name is a combination of curies, mixins, etc. on the
     * message plus a suffix.  The event payload will be a PbjxEvent or a
     * subclass of that.
     *
     * @see \Gdbots\Pbjx\Event\PbjxEvent
     * @var string
     */
    const SUFFIX_BIND = 'bind';
    const SUFFIX_VALIDATE = 'validate';
    const SUFFIX_ENRICH = 'enrich';
    const SUFFIX_BEFORE_HANDLE = 'before_handle';
    const SUFFIX_AFTER_HANDLE = 'after_handle';
    const SUFFIX_CREATED = 'created';
    const SUFFIX_UPDATED = 'updated';
    const SUFFIX_DELETED = 'deleted';

    /**
     * Occurs prior to an expection being thrown during the handling phase of a command.
     * This is not announced during validate, enrich or the transport send.
     *
     * @see \Gdbots\Pbjx\Event\BusExceptionEvent
     * @var string
     */
    const COMMAND_BUS_EXCEPTION = 'gdbots_pbjx.command_bus.exception';

    /**
     * Occurs during event dispatching, where events are actually handled.  If the
     * subscriber throws an exception and the EventExecutionFailed also fails
     * to be handled, then this event is announced.  This should be very rare.
     *
     * @see \Gdbots\Pbjx\Event\BusExceptionEvent
     * @var string
     */
    const EVENT_BUS_EXCEPTION = 'gdbots_pbjx.event_bus.exception';

    /**
     * Occurs during request handling when an exception is not properly
     * handled and converted to a RequestFailedResponse response.
     *
     * @see \Gdbots\Pbjx\Event\BusExceptionEvent
     * @var string
     */
    const REQUEST_BUS_EXCEPTION = 'gdbots_pbjx.request_bus.exception';

    /**
     * Occurs prior to the message being sent by the transport.
     *
     * @see \Gdbots\Pbjx\Event\TransportEvent
     * @var string
     */
    const TRANSPORT_BEFORE_SEND = 'gdbots_pbjx.transport.before_send';

    /**
     * Occurs after the message has been successfully sent by the transport.
     *
     * @see \Gdbots\Pbjx\Event\TransportEvent
     * @var string
     */
    const TRANSPORT_AFTER_SEND = 'gdbots_pbjx.transport.after_send';

    /**
     * Occurs if the transport throws an exception during the send.
     *
     * @see \Gdbots\Pbjx\Event\TransportExceptionEvent
     * @var string
     */
    const TRANSPORT_SEND_EXCEPTION = 'gdbots_pbjx.transport.send_exception';

    /**
     * Occurs before a job/task/message has been handled by a consumer.
     *
     * @see \Symfony\Component\EventDispatcher\Event
     * @var string
     */
    const CONSUMER_BEFORE_HANDLE = 'gdbots_pbjx.consumer.before_handle';

    /**
     * Occurs after a job/task/message has been handled by a consumer.
     *
     * @see \Symfony\Component\EventDispatcher\Event
     * @var string
     */
    const CONSUMER_AFTER_HANDLE = 'gdbots_pbjx.consumer.after_handle';

    /**
     * Occurs if an exception is thrown during message handling.
     *
     * @see \Symfony\Component\EventDispatcher\Event
     * @var string
     */
    const CONSUMER_HANDLING_EXCEPTION = 'gdbots_pbjx.consumer.handling_exception';

    /**
     * Occurs after the consumer has stopped and finished its teardown.
     *
     * @see \Symfony\Component\EventDispatcher\Event
     * @var string
     */
    const CONSUMER_AFTER_TEARDOWN = 'gdbots_pbjx.consumer.after_teardown';
}
