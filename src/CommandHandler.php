<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Schemas\Pbj\Command\Command;

interface CommandHandler
{
    /**
     * This method is implicitly required, it's just not enforced by this interface.
     * Now, with no extra hassle you can make the type hinting on your handler
     * exactly how you want it so long as the first two arguments are a
     * Request and then the Pbjx service.
     *
     * It is HIGHLY recommended that the method have no other arguments, so in
     * the future we can add more if needed.
     *
     * @param Command $command
     * @param Pbjx $pbjx
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    // example of VALID handle methods:
    //public function handle(MyCommand $command, Pbjx $pbjx);
    //public function handle(MyOtherCommand $command);

    // example of INVALID methods:
    //public function handle(SomeCommand $command, SomeOtherClass $klass); // WRONG - Pbjx is not the second arg.
    //public function handle(NotACommand $thing, Pbjx $pbjx); // WRONG - first arg is not a command
    //public function handle(SomeCommand $command, Pbjx $pbjx, $thirdArg); // WRONG - third arg, don't do this!
}
