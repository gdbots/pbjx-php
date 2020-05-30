<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\Pbjx;

class SayHelloHandler implements CommandHandler
{
    private ?Message $handled = null;

    public static function handlesCuries(): array
    {
        return ['gdbots:tests.pbjx:fixtures:say-hello'];
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
