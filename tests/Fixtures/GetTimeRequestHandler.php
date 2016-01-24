<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbjx\RequestHandler;

class GetTimeRequestHandler implements RequestHandler
{
    /**
     * @param GetTimeRequest $request
     *
     * @return string|static
     */
    public function handle(GetTimeRequest $request)
    {
        if ($request->get('test_fail')) {
            return 'test fail';
        }

        return GetTimeResponse::create()->set('time', $request->get('microtime')->toDateTime());
    }
}
