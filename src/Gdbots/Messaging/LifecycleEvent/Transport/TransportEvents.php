<?php

namespace Gdbots\Messaging\LifecycleEvent\Transport;

final class TransportEvents
{
    /**
     * Private constructor. This class is not meant to be instantiated.
     */
    private function __construct() {}

    /**
     * Occurs prior to the message being sent by the transport.
     * Event listener will receive Gdbots\Messaging\LifecycleEvent\Transport\TransportEvent
     *
     * @var string
     */
    const BEFORE_SEND = 'tdn.messaging.transport.before_send';

    /**
     * Occurs after the message has been successfully sent by the transport.
     * Event listener will receive Gdbots\Messaging\LifecycleEvent\Transport\TransportEvent
     *
     * @var string
     */
    const AFTER_SEND = 'tdn.messaging.transport.after_send';

    /**
     * Occurs if the transport throws an exception during the send.
     * Event listener will receive Gdbots\Messaging\LifecycleEvent\Transport\TransportExceptionEvent
     *
     * @var string
     */
    const EXCEPTION = 'tdn.messaging.transport.exception';
}
