<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\CommandHandlerTrait;

class SayHelloHandler implements CommandHandler
{
    use CommandHandlerTrait;

    /** @var SayHello */
    private $handled;

    /**
     * @param SayHello $command
     */
    protected function handle(SayHello $command)
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
