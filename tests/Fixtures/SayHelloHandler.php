<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\Pbjx;

class SayHelloHandler implements CommandHandler
{
    /** @var SayHello */
    private $handled;

    /**
     * @param SayHello $command
     * @param Pbjx $pbjx
     */
    public function handle(SayHello $command, Pbjx $pbjx)
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
