<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

final class UnexpectedValueException extends \UnexpectedValueException implements GdbotsPbjxException
{
    public function __construct(string $message = '', int $code = 11, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
