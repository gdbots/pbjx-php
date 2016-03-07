<?php

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Schemas\Pbjx\Event\EventExecutionFailedV1;
use Gdbots\Tests\Pbjx\Fixtures\FailingEvent;
use Gdbots\Tests\Pbjx\Fixtures\SimpleEvent;

class DefaultEventBusTest extends AbstractBusTestCase
{
    public function testPublish()
    {
        $event = SimpleEvent::create()->set('name', 'homer');
        $that = $this;
        $dispatcher = $this->locator->getDispatcher();

        $schemaId = $event::schema()->getId();
        $curie = $schemaId->getCurie();
        $vendor = $curie->getVendor();
        $package = $curie->getPackage();
        $category = $curie->getCategory();
        $called = 0;

        $func = function (SimpleEvent $publishedEvent) use ($that, $event, &$called) {
            $called++;
            $that->assertSame($publishedEvent, $event);
        };

        $dispatcher->addListener($schemaId->getCurieWithMajorRev(), $func);
        $dispatcher->addListener($curie->toString(), $func);
        $dispatcher->addListener(sprintf('%s:%s:%s:*', $vendor, $package, $category), $func);
        $dispatcher->addListener(sprintf('%s:%s:*', $vendor, $package), $func);
        $dispatcher->addListener(sprintf('%s:*', $vendor), $func);
        $this->pbjx->publish($event);

        $this->assertEquals(5, $called);
    }

    public function testEventExecutionFailed()
    {
        $event = FailingEvent::create()->set('name', 'homer');
        $dispatcher = $this->locator->getDispatcher();
        $schemaId = $event::schema()->getId();
        $handled = false;

        $dispatcher->addListener(
            $schemaId->getCurieWithMajorRev(),
            function () {
                throw new \LogicException('Simulate failure 1.');
            }
        );

        $dispatcher->addListener(
            EventExecutionFailedV1::schema()->getCurieWithMajorRev(),
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
                $schemaId->getCurieWithMajorRev()
            )
        );
    }

    public function testEventBusExceptionEvent()
    {
        $event = FailingEvent::create()->set('name', 'marge');
        $that = $this;
        $dispatcher = $this->locator->getDispatcher();
        $schemaId = $event::schema()->getId();

        $dispatcher->addListener(
            $schemaId->getCurieWithMajorRev(),
            function () {
                throw new \LogicException('Simulate failure 2.');
            }
        );

        $dispatcher->addListener(
            EventExecutionFailedV1::schema()->getCurieWithMajorRev(),
            function () {
                throw new \LogicException('Failed to handle EventExecutionFailedV1.');
            }
        );

        $dispatcher->addListener(
            PbjxEvents::EVENT_BUS_EXCEPTION,
            function (BusExceptionEvent $exceptionEvent) use ($that, $event) {
                /** @var EventExecutionFailedV1 $domainEvent */
                $domainEvent = $exceptionEvent->getMessage();
                $that->assertSame(
                    $domainEvent->get('event')->get('name'),
                    $event->get('name')
                );
            }
        );

        $this->pbjx->publish($event);
    }
}
