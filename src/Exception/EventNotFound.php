<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Exception;

use Gdbots\Pbj\Exception\HasEndUserMessage;
use Gdbots\Schemas\Pbjx\Enum\Code;

final class EventNotFound extends EventStoreOperationFailed implements HasEndUserMessage
{
    /**
     * @param string     $message
     * @param \Exception $previous
     */
    public function __construct(string $message = 'Event not found', ?\Exception $previous = null)
    {
        parent::__construct($message, Code::NOT_FOUND, $previous);
    }

    /**
     * {@inheritdoc}
     */
    public function getEndUserMessage()
    {
        return $this->getMessage();
    }

    /**
     * {@inheritdoc}
     */
    public function getEndUserHelpLink()
    {
        return null;
    }
}
