<?php

namespace Gdbots\PbjxBack\RequestBus;

use Rhumsaa\Uuid\Uuid;
use Gdbots\PbjxBack\AbstractMessage;

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