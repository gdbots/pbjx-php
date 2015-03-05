<?php

namespace Gdbots\Tests\Pbjx\Fixtures;

use Gdbots\Pbjx\ConventionalRequestHandling;
use Gdbots\Pbjx\Notifier;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RequestHandler;

class GetTimeRequestHandler implements RequestHandler
{
    use ConventionalRequestHandling;

    /**
     * @param GetTimeRequest $request
     * @param Pbjx $pbjx
     * @param Notifier $notifier
     *
     * @return GetTimeResponse
     */
    protected function getTimeRequest(GetTimeRequest $request, Pbjx $pbjx, Notifier $notifier)
    {
        $response = GetTimeResponse::create()
            ->setTime($request->getMicrotime()->toDateTime())
        ;

        for ($i = 0; $i < 5; $i++) {
            $notifier->notify('doing stuff - ' . $i);
            //sleep(1);
        }

        return $response;
    }
}
