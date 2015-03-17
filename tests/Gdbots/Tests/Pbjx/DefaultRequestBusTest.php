<?php

namespace Gdbots\Tests\Pbjx;

use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequest;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequestHandler;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeResponse;

class DefaultRequestBusTest extends AbstractBusTestCase
{
    protected function setup()
    {
        parent::setup();
        $this->locator->registerRequestHandler(
            GetTimeRequest::schema()->getCurie(),
            new GetTimeRequestHandler()
        );
    }

    public function testRequest()
    {
        $request = GetTimeRequest::create();
        $expected = $request->getMicrotime()->toDateTime();
        $promise = $this->pbjx->request($request);

        $promise->then(function(GetTimeResponse $response) use ($expected) {
            $this->assertSame($expected, $response->getTime());
        });
    }

    public function testRequestHandlingFailed()
    {
        $request = GetTimeRequest::create();
        $request->setTestFail(true);
        $promise = $this->pbjx->request($request);

        $promise->then(function() {
            $this->fail('Request did not fail as expected.');
        });
    }
}
