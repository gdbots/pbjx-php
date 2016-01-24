<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestHandler;

class GetTimeRequestHandler implements RequestHandler
{
    /**
     * @param GetTimeRequest $request
     * @param Pbjx $pbjx
     *
     * @return string|static
     */
    public function handle(GetTimeRequest $request, Pbjx $pbjx)
    {
        if ($request->get('test_fail')) {
            return 'test fail';
        }

        return GetTimeResponse::create()->set('time', $request->get('microtime')->toDateTime());
    }
}
