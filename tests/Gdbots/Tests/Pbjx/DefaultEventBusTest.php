<?php

namespace Gdbots\Tests\Pbjx;

use Gdbots\Pbjx\Pbjx;
use Gdbots\Tests\Pbjx\Fixtures\SimpleEvent;
use Gdbots\Tests\Pbjx\Mock\ServiceLocatorMock;

class DefaultEventBusTest extends \PHPUnit_Framework_TestCase
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

    public function testPublish()
    {
        $event = SimpleEvent::create()->setName('homer');
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

        $dispatcher->addListener($schemaId->getResolverKey(), $func);
        $dispatcher->addListener($curie->toString(), $func);
        $dispatcher->addListener(sprintf('%s:%s:%s:*', $vendor, $package, $category), $func);
        $dispatcher->addListener(sprintf('%s:%s:*', $vendor, $package), $func);
        $dispatcher->addListener(sprintf('%s:*', $vendor), $func);
        $this->pbjx->publish($event);

        $this->assertEquals(5, $called);
    }
}
