<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbjx\CommandHandler;
use Gdbots\Pbjx\CommandHandlerTrait;
use Gdbots\Pbjx\Pbjx;

class SayHelloHandler implements CommandHandler
{
    use CommandHandlerTrait;

    /** @var SayHello */
    private $handled;

    /**
     * @param SayHello $command
     * @param Pbjx     $pbjx
     */
    protected function handle(SayHello $command, Pbjx $pbjx): void
    {
        $this->handled = $command;
    }

    /**
     * @param SayHello $command
     *
     * @return bool
     */
    public function hasHandled(SayHello $command): bool
    {
        return $this->handled === $command;
    }
}
