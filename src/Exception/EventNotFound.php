<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

use Gdbots\Schemas\Pbjx\Enum\Code;

final class EventNotFound extends EventStoreOperationFailed
{
    public function __construct(string $message = 'Event not found', ?\Throwable $previous = null)
    {
        parent::__construct($message, Code::NOT_FOUND, $previous);
    }
}
