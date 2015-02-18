<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\Pbjx;

class SayHelloHandler implements CommandHandler
{
    private $handled;

    public function handleCommand(Command $command, Pbjx $pbjx)
    {
        $this->handled = $command;
    }

    public function hasHandled(Command $command)
    {
        return $this->handled === $command;
    }
}
