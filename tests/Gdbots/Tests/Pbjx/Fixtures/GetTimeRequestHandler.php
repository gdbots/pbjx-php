<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbjx\ConventionalRequestHandling;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestHandler;

class GetTimeRequestHandler implements RequestHandler
{
    use ConventionalRequestHandling;

    /**
     * @param GetTimeRequest $request
     * @param Pbjx $pbjx
     *
     * @return GetTimeResponse
     */
    protected function getTimeRequest(GetTimeRequest $request, Pbjx $pbjx)
    {
        if ($request->getTestFail()) {
            return 'test fail';
        }
        return GetTimeResponse::create()->setTime($request->getMicrotime()->toDateTime());
    }
}
