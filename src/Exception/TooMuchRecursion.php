<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx\Exception;

use Gdbots\Schemas\Pbjx\Enum\Code;

final class TooMuchRecursion extends LogicException implements GdbotsPbjxException
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message, Code::INVALID_ARGUMENT);
    }
}
