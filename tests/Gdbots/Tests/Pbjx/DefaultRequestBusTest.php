<?php

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbjx\Pbjx;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequest;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequestHandler;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeResponse;
use Gdbots\Tests\Pbjx\Mock\ServiceLocatorMock;

class DefaultRequestBusTest extends \PHPUnit_Framework_TestCase
{
    /** @var ServiceLocatorMock */
    protected $locator;

    /** @var Pbjx */
    protected $pbjx;

    protected function setup()
    {
        $this->locator = new ServiceLocatorMock();
        $this->pbjx = $this->locator->getPbjx();
        $this->locator->registerRequestHandler(
            GetTimeRequest::schema()->getId()->getCurie(),
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
