<?php

namespace Gdbots\Pbjx\RequestBus;

use Rhumsaa\Uuid\Uuid;
use Gdbots\Pbjx\AbstractMessage;

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