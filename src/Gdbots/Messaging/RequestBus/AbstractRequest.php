<?php

namespace Gdbots\Messaging\RequestBus;

use Rhumsaa\Uuid\Uuid;
use Gdbots\Messaging\AbstractMessage;

abstract class AbstractRequest extends AbstractMessage implements RequestInterface
{
    /**
     * @return Uuid
     */
    protected function generateMessageId()
    {
        return Uuid::uuid4();
    }
}