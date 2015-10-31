<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\DomainCommand;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\ConventionalCommandHandling;

class SayHelloHandler implements CommandHandler
{
    use ConventionalCommandHandling;

    private $handled;

    /**
     * @param SayHello $command
     *
     * @throws \Exception
     */
    protected function sayHello(SayHello $command)
    {
        $this->handled = $command;
    }

    public function hasHandled(DomainCommand $command)
    {
        return $this->handled === $command;
    }
}
