<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbjx\ConventionalRequestHandling;
use Gdbots\Pbjx\RequestHandler;

class GetTimeRequestHandler implements RequestHandler
{
    use ConventionalRequestHandling;

    /**
     * @param GetTimeRequest $request
     *
     * @return GetTimeResponse
     */
    protected function getTimeRequest(GetTimeRequest $request)
    {
        if ($request->getTestFail()) {
            return 'test fail';
        }
        return GetTimeResponse::create()->setTime($request->getMicrotime()->toDateTime());
    }
}
