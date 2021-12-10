<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

use Gdbots\Schemas\Pbjx\Enum\Code;

final class OptimisticCheckFailed extends \RuntimeException implements GdbotsPbjxException
{
    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message, Code::FAILED_PRECONDITION->value, $previous);
    }
}
