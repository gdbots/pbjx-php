<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbjx\RequestHandler;
use Gdbots\Pbjx\RequestHandlerTrait;

class GetTimeRequestHandler implements RequestHandler
{
    use RequestHandlerTrait;

    /**
     * @param GetTimeRequest $request
     *
     * @return string|GetTimeResponse
     */
    protected function handle(GetTimeRequest $request)
    {
        if ($request->get('test_fail')) {
            return 'test fail';
        }

        return GetTimeResponse::create()->set('time', $request->get('occurred_at')->toDateTime());
    }
}
