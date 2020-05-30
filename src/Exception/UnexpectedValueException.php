<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

use Gdbots\Schemas\Pbjx\Enum\Code;

final class UnexpectedValueException extends \UnexpectedValueException implements GdbotsPbjxException
{
    public function __construct(string $message = '', int $code = Code::OUT_OF_RANGE, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
