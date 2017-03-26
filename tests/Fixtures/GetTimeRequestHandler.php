<?php
declare(strict_types = 1);

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestHandler;
use Gdbots\Pbjx\RequestHandlerTrait;

class GetTimeRequestHandler implements RequestHandler
{
    use RequestHandlerTrait;

    /**
     * @param GetTimeRequest $request
     * @param Pbjx           $pbjx
     *
     * @return GetTimeResponse
     *
     * @throws \Exception
     */
    protected function handle(GetTimeRequest $request, Pbjx $pbjx): GetTimeResponse
    {
        if ($request->get('test_fail')) {
            throw new \Exception('test fail');
        }

        return GetTimeResponse::create()->set('time', $request->get('occurred_at')->toDateTime());
    }
}
