<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx;

use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequest;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequestHandler;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeResponse;

class SimpleRequestBusTest extends AbstractBusTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->locator->registerRequestHandler(
            GetTimeRequest::schema()->getCurie(),
            new GetTimeRequestHandler()
        );
    }

    public function testRequest()
    {
        $request = GetTimeRequest::create();
        /** @var \DateTimeInterface $expected */
        $expected = $request->get('occurred_at')->toDateTime();
        /** @var GetTimeResponse $response */
        $response = $this->pbjx->request($request);
        $this->assertInstanceOf(GetTimeResponse::class, $response);
        $this->assertSame($expected->format('U.u'), $response->get('time')->format('U.u'));
    }

    public function testRequestHandlingFailed()
    {
        $request = GetTimeRequest::create();
        $request->set('test_fail', true);

        try {
            $this->pbjx->request($request);
            $this->fail('Request did not fail as expected.');
        } catch (\Throwable $e) {
            $this->assertTrue(true, 'Request failed as expected.');
        }
    }
}
