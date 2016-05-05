<?php

namespace Gdbots\Pbjx\Exception;

use Gdbots\Schemas\Pbjx\Enum\Code;

final class OptimisticCheckFailed extends \RuntimeException implements GdbotsPbjxException
{
    /**
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct($message = '', $code = Code::FAILED_PRECONDITION, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
