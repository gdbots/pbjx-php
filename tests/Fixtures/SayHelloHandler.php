<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbj\DomainCommand;
use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\ConventionalCommandHandling;
use Gdbots\Pbjx\Pbjx;

class SayHelloHandler implements CommandHandler
{
    use ConventionalCommandHandling;

    private $handled;

    /**
     * @param SayHello $command
     * @param Pbjx $pbjx
     *
     * @throws \Exception
     */
    protected function sayHello(SayHello $command, Pbjx $pbjx)
    {
        $this->handled = $command;
    }

    public function hasHandled(DomainCommand $command)
    {
        return $this->handled === $command;
    }
}