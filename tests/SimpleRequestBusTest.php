<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbj\SchemaCurie;
use Gdbots\Schemas\Pbjx\Request\EchoRequestV1;
use Gdbots\Schemas\Pbjx\Request\EchoResponseV1;
use Gdbots\Tests\Pbjx\Fixtures\EchoRequestHandler;

class SimpleRequestBusTest extends AbstractBusTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->locator->registerRequestHandler(
            SchemaCurie::fromString(EchoRequestV1::SCHEMA_CURIE),
            new EchoRequestHandler()
        );
    }

    public function testRequest()
    {
        $request = EchoRequestV1::create();
        /** @var \DateTimeInterface $expected */
        $expected = $request->get('occurred_at')->toDateTime();
        $response = $this->pbjx->request($request);
        $this->assertInstanceOf(EchoResponseV1::class, $response);
        $this->assertSame($expected->format('U.u'), $response->get('msg'));
    }

    public function testRequestHandlingFailed()
    {
        $request = EchoRequestV1::create();
        $request->set('msg', 'fail');

        try {
            $this->pbjx->request($request);
            $this->fail('Request did not fail as expected.');
        } catch (\Throwable $e) {
            $this->assertTrue(true, 'Request failed as expected.');
        }
    }
}
