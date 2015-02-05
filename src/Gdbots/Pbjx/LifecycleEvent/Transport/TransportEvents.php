<?php

namespace Gdbots\Pbjx\LifecycleEvent\Transport;

final class TransportEvents
{
    /**
     * Private constructor. This class is not meant to be instantiated.
     */
    private function __construct() {}

    /**
     * Occurs prior to the message being sent by the transport.
     * Event listener will receive Gdbots\Pbjx\LifecycleEvent\Transport\TransportEvent
     *
     * @var string
     */
    const BEFORE_SEND = 'gdbots.messaging.transport.before_send';

    /**
     * Occurs after the message has been successfully sent by the transport.
     * Event listener will receive Gdbots\Pbjx\LifecycleEvent\Transport\TransportEvent
     *
     * @var string
     */
    const AFTER_SEND = 'gdbots.messaging.transport.after_send';

    /**
     * Occurs if the transport throws an exception during the send.
     * Event listener will receive Gdbots\Pbjx\LifecycleEvent\Transport\TransportExceptionEvent
     *
     * @var string
     */
    const EXCEPTION = 'gdbots.messaging.transport.exception';
}
