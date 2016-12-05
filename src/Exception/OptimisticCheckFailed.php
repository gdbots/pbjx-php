<?php

namespace Gdbots\Pbjx\Exception;

use Gdbots\Schemas\Pbjx\Enum\Code;

final class OptimisticCheckFailed extends \RuntimeException implements GdbotsPbjxException
{
    /**
     * @param string $message
     * @param \Exception|null $previous
     */
    public function __construct($message = '', \Exception $previous = null)
    {
        parent::__construct($message, Code::FAILED_PRECONDITION, $previous);
    }
}
