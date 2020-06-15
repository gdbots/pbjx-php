<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Schemas\Pbjx\Command\CheckHealthV1;

class CheckHealthHandler implements CommandHandler
{
    private ?Message $handled = null;

    public static function handlesCuries(): array
    {
        return [CheckHealthV1::SCHEMA_CURIE];
    }

    public function handleCommand(Message $command, Pbjx $pbjx): void
    {
        $this->handled = $command;
    }

    public function hasHandled(Message $command): bool
    {
        return $this->handled === $command;
    }
}
