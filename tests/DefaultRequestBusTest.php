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
        /** @var GetTimeResponse $response */
        $response = $this->pbjx->request($request);
        $this->assertInstanceOf('Gdbots\Tests\Pbjx\Fixtures\GetTimeResponse', $response);
        $this->assertSame($expected->format('U.u'), $response->getTime()->format('U.u'));
    }

    public function testRequestHandlingFailed()
    {
        $request = GetTimeRequest::create();
        $request->setTestFail(true);

        try {
            $response = $this->pbjx->request($request);
            $this->fail('Request did not fail as expected.');
        } catch (\Exception $e) {
        }
    }
}
