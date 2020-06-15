<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Schemas\Pbjx\Event\EventExecutionFailedV1;
use Gdbots\Schemas\Pbjx\Event\HealthCheckedV1;

class SimpleEventBusTest extends AbstractBusTestCase
{
    public function testPublish(): void
    {
        $event = HealthCheckedV1::create()->set(HealthCheckedV1::MSG_FIELD, 'homer');
        $that = $this;
        $dispatcher = $this->locator->getDispatcher();

        $schemaId = $event::schema()->getId();
        $curie = $schemaId->getCurie();
        $called = 0;

        $func = function (Message $publishedEvent) use ($that, $event, &$called) {
            $called++;
            $that->assertSame($publishedEvent, $event);
        };

        $dispatcher->addListener($schemaId->getCurieMajor(), $func);
        $dispatcher->addListener($curie->toString(), $func);
        $dispatcher->addListener("{$curie->getVendor()}:{$curie->getPackage()}:*", $func);
        $dispatcher->addListener('*', $func);
        $this->pbjx->publish($event);

        $this->assertEquals(4, $called);
    }

    public function testEventExecutionFailed(): void
    {
        $event = HealthCheckedV1::create()->set(HealthCheckedV1::MSG_FIELD, 'homer');
        $dispatcher = $this->locator->getDispatcher();
        $schemaId = $event::schema()->getId();
        $handled = false;

        $dispatcher->addListener(
            $schemaId->getCurieMajor(),
            function () {
                throw new \LogicException('Simulate failure 1.');
            }
        );

        $dispatcher->addListener(
            EventExecutionFailedV1::schema()->getCurieMajor(),
            function () use (&$handled) {
                $handled = true;
            }
        );

        $this->pbjx->publish($event);
        $this->assertTrue(
            $handled,
            sprintf(
                '%s failed because the event [%s] was never published.',
                __FUNCTION__,
                $schemaId->getCurieMajor()
            )
        );
    }

    public function testEventBusExceptionEvent()
    {
        $event = HealthCheckedV1::create()->set(HealthCheckedV1::MSG_FIELD, 'marge');
        $that = $this;
        $dispatcher = $this->locator->getDispatcher();
        $schemaId = $event::schema()->getId();

        $dispatcher->addListener(
            $schemaId->getCurieMajor(),
            function () {
                throw new \LogicException('Simulate failure 2.');
            }
        );

        $dispatcher->addListener(
            EventExecutionFailedV1::schema()->getCurieMajor(),
            function () {
                throw new \LogicException('Failed to handle EventExecutionFailedV1.');
            }
        );

        $dispatcher->addListener(
            PbjxEvents::EVENT_BUS_EXCEPTION,
            function (BusExceptionEvent $exceptionEvent) use ($that, $event) {
                $domainEvent = $exceptionEvent->getMessage();
                $that->assertSame(
                    $domainEvent->get(EventExecutionFailedV1::EVENT_FIELD)->get(HealthCheckedV1::MSG_FIELD),
                    $event->get(HealthCheckedV1::MSG_FIELD)
                );
            }
        );

        $this->pbjx->publish($event);
    }
}
