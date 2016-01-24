<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\Schemas\Pbj\Request\Request;
use Gdbots\Schemas\Pbj\Request\Response;

interface RequestHandler
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
     * @param Request $request
     * @param Pbjx $pbjx
     * @return Response
     *
     * @throws GdbotsPbjxException
     * @throws \Exception
     */
    // example of VALID handle methods:
    //public function handle(MyRequest $request, Pbjx $pbjx);
    //public function handle(MyOtherRequest $request);

    // example of INVALID methods:
    //public function handle(SomeRequest $request, SomeOtherClass $klass); // WRONG - Pbjx is not the second arg.
    //public function handle(NotARequest $thing, Pbjx $pbjx); // WRONG - first arg is not a request
    //public function handle(SomeRequest $request, Pbjx $pbjx, $thirdArg); // WRONG - third arg, don't do this!
}
