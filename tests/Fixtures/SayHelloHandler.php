<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbjx\CommandHandler;

class SayHelloHandler implements CommandHandler
{
    /** @var SayHello */
    private $handled;

    /**
     * @param SayHello $command
     */
    public function handle(SayHello $command)
    {
        $this->handled = $command;
    }

    /**
     * @param SayHello $command
     *
     * @return bool
     */
    public function hasHandled(SayHello $command)
    {
        return $this->handled === $command;
    }
}
