<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

class LogicException extends \LogicException implements GdbotsPbjxException
{
    public function __construct(string $message = '', int $code = 13, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
