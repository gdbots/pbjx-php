<?php

namespace Gdbots\Pbjx\Exception;

use Gdbots\Schemas\Pbjx\Enum\Code;

class InvalidArgumentException extends \InvalidArgumentException implements GdbotsPbjxException
{
    /**
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct($message = '', $code = Code::INVALID_ARGUMENT, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
