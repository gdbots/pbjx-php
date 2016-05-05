<?php

namespace Gdbots\Pbjx\Exception;

use Gdbots\Schemas\Pbjx\Enum\Code;

class EventStoreOperationFailed extends \RuntimeException implements GdbotsPbjxException
{
    /**
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct($message = '', $code = Code::INTERNAL, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
