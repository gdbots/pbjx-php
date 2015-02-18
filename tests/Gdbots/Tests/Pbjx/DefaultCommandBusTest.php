<?php

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbjx\Pbjx;
use Gdbots\Tests\Pbjx\Fixtures\SayHello;
use Gdbots\Tests\Pbjx\Fixtures\SayHelloHandler;
use Gdbots\Tests\Pbjx\Mock\ServiceLocatorMock;

class DefaultCommandBusTest extends \PHPUnit_Framework_TestCase
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

    public function testSend()
    {
        $command = SayHello::create()->setName('homer');
        $handler = new SayHelloHandler();
        $this->locator->registerCommandHandler($command::schema()->getId()->getCurie(), $handler);
        $this->pbjx->send($command);
        $this->assertTrue($handler->hasHandled($command));
    }
}
