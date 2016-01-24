<?php

namespace Gdbots\Pbjx;

/**
 * RequestHandler is for the most part a marker/tag interface.
 * @link https://en.wikipedia.org/wiki/Marker_interface_pattern
 *
 * There is one implicitly required method and that's the "handle" method.
 * You can make the type hinting on your handler exactly how you want it
 * so long as the first two arguments are a Request and then the Pbjx service.
 *
 * It is HIGHLY recommended that the method have no other arguments, so in
 * the future we can add more if needed.
 *
 * Example of VALID handle methods:
 *  - public function handle(MyRequest $request, Pbjx $pbjx);
 *  - public function handle(MyOtherRequest $request);
 *
 * Example of INVALID methods:
 *  - public function handle(SomeRequest $request, SomeOtherClass $klass); // WRONG - Pbjx is not the second arg.
 *  - public function handle(NotARequest $thing, Pbjx $pbjx); // WRONG - first arg is not a request
 *  - public function handle(SomeRequest $request, Pbjx $pbjx, $thirdArg); // WRONG - third arg, don't do this!
 */
interface RequestHandler
{
    /*
     * This method is implicitly required, it's just not enforced by this interface.
     */
    //public function handle(MyRequest $request, Pbjx $pbjx);
}
