<?php

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Tests\Pbjx\Fixtures\FakeCommand;
use Gdbots\Tests\Pbjx\Fixtures\SayHello;
use Gdbots\Tests\Pbjx\Fixtures\SayHelloHandler;

class DefaultCommandBusTest extends AbstractBusTestCase
{
    public function testSend()
    {
        $command = SayHello::create();
        $handler = new SayHelloHandler();
        $this->locator->registerCommandHandler($command::schema()->getCurie(), $handler);
        $this->pbjx->send($command);
        $this->assertTrue($handler->hasHandled($command));
    }

    /**
     * @expectedException \Gdbots\Pbjx\Exception\HandlerNotFound
     */
    public function testReceiveCommandWithNoHandler()
    {
        $command = FakeCommand::create();
        $this->locator->getDispatcher()->addListener(
            PbjxEvents::COMMAND_BUS_EXCEPTION,
            function(BusExceptionEvent $event) {
                throw $event->getException();
            }
        );
        $this->locator->getCommandBus()->receiveCommand($command);
    }
}
