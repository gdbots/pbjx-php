<?php

namespace Gdbots\Pbjx\Exception;

use Gdbots\Schemas\Pbjx\Enum\Code;

class UnexpectedValueException extends \UnexpectedValueException implements GdbotsPbjxException
{
    /**
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct($message = '', $code = Code::OUT_OF_RANGE, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
