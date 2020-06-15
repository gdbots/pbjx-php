<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Exception\HandlerNotFound;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Schemas\Pbjx\Command\CheckHealthV1;
use Gdbots\Tests\Pbjx\Fixtures\CheckHealthHandler;

class SimpleCommandBusTest extends AbstractBusTestCase
{
    public function testSend()
    {
        $command = CheckHealthV1::create()->set(CheckHealthV1::MSG_FIELD, 'homer');
        $handler = new CheckHealthHandler();
        $this->locator->registerCommandHandler($command::schema()->getCurie(), $handler);
        $this->pbjx->send($command);
        $this->assertTrue($handler->hasHandled($command));
    }

    public function testReceiveCommandWithNoHandler()
    {
        $this->expectException(HandlerNotFound::class);
        $command = CheckHealthV1::create();
        $this->locator->getDispatcher()->addListener(
            PbjxEvents::COMMAND_BUS_EXCEPTION,
            function (BusExceptionEvent $event) {
                throw $event->getException();
            }
        );
        $this->locator->getCommandBus()->receiveCommand($command);
    }
}
