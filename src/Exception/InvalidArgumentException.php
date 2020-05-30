<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

use Gdbots\Schemas\Pbjx\Enum\Code;

final class InvalidArgumentException extends \InvalidArgumentException implements GdbotsPbjxException
{
    public function __construct(string $message = '', int $code = Code::INVALID_ARGUMENT, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
