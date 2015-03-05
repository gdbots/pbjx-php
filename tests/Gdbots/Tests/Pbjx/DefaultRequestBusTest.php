<?php

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbjx\Exception\RequestHandlingFailed;
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
    }

    public function testRequest()
    {
        $request = GetTimeRequest::create();
        $handler = new GetTimeRequestHandler();
        $this->locator->registerRequestHandler($request::schema()->getId()->getCurie(), $handler);
        $promise = $this->pbjx->request($request);
        $promise->then(
            function(GetTimeResponse $response) {
                echo $response->getTime()->format('h:ia');
            },

            function(RequestHandlingFailed $e) {
                echo $e->getMessage() . PHP_EOL;
                echo $e->getRequest();
            },

            function ($value) {
                var_dump($value);
            }
        );
    }

}
