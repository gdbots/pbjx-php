<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

final class InvalidArgumentException extends \InvalidArgumentException implements GdbotsPbjxException
{
    public function __construct(string $message = '', int $code = 3, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
