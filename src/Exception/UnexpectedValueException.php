<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

use Gdbots\Schemas\Pbjx\Enum\Code;

final class UnexpectedValueException extends \UnexpectedValueException implements GdbotsPbjxException
{
    /**
     * @param string     $message
     * @param int        $code
     * @param \Exception $previous
     */
    public function __construct(string $message = '', int $code = Code::OUT_OF_RANGE, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
