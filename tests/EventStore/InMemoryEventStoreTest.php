<?php
declare(strict_types = 1);

namespace Gdbots\Tests\Pbjx\EventStore;

use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Pbjx\EventStore\InMemoryEventStore;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RegisteringServiceLocator;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Gdbots\Tests\Pbjx\Fixtures\SimpleEvent;

class InMemoryEventStoreTest extends \PHPUnit_Framework_TestCase
{
    /** @var RegisteringServiceLocator */
    protected $locator;

    /** @var Pbjx */
    protected $pbjx;

    /** @var InMemoryEventStore */
    protected $store;

    protected function setup()
    {
        $this->locator = new RegisteringServiceLocator();
        $this->pbjx = $this->locator->getPbjx();
        $this->store = new InMemoryEventStore($this->pbjx);
        $this->locator->setEventStore($this->store);
    }

    public function testGetStreamSlice()
    {
        $streamId = StreamId::fromString('test');
        $store = $this->pbjx->getEventStore();
        $since = Microtime::create();

        $store->putEvents($streamId, [
            SimpleEvent::fromArray([
                'occurred_at' => '1489129155504330',
                'name'        => 'past event',
            ]),

            SimpleEvent::fromArray([
                'occurred_at' => '2489129155504330',
                'name'        => 'future event',
            ]),
        ]);

        // get the future event
        $slice = $store->getStreamSlice($streamId, $since);
        $this->assertSame(1, $slice->count());

        // get the past event
        $slice = $store->getStreamSlice($streamId, $since, 25, false);
        $this->assertSame(1, $slice->count());

        // get both
        $slice = $store->getStreamSlice($streamId);
        $this->assertSame(2, $slice->count());
    }

    public function testPutEvents()
    {
        $streamId = StreamId::fromString('test.put');
        $start = Microtime::create();
        $events = [];

        for ($i = 0; $i < 10; $i++) {
            $events[] = SimpleEvent::create()->set('name', 'iter' . $i);
        }

        // make sure stream contains all events after put
        $this->store->putEvents($streamId, $events);
        $slice = $this->store->getStreamSlice($streamId);
        $this->assertSame(iterator_to_array($slice->getIterator()), $events);

        // ensure stream slice is in correct forward order
        $since = $start;
        foreach ($events as $event) {
            $slice = $this->store->getStreamSlice($streamId, $since, 1);
            $since = $slice->getLastOccurredAt();
        }

        // ensure stream slice is in correct reverse order
        $events = array_reverse($events);
        foreach ($events as $event) {
            $slice = $this->store->getStreamSlice($streamId, $since, 1, false);
            $since = $slice->getLastOccurredAt();
        }
    }

    public function testPipeEvents()
    {
        $streamId = StreamId::fromString('test.pipe');
        $events = [];

        for ($i = 0; $i < 100; $i++) {
            $events['iter' . $i] = SimpleEvent::create()->set('name', 'iter' . $i);
        }

        $receiver = function (Event $event, StreamId $streamId) use (&$events) {
            unset($events[$event->get('name')]);
        };

        $this->store->putEvents($streamId, $events);
        $this->store->pipeEvents($streamId, $receiver);
        $this->assertEmpty($events);
    }

    public function testPipeAllEvents()
    {
        $this->store->clear();
        $expected = 0;

        for ($i = 0; $i < 10; $i++) {
            $streamId = StreamId::fromString('test.pipe-all:' . $i);
            $events = [];

            for ($j = 0; $j < 100; $j++) {
                $events[] = SimpleEvent::create()->set('name', 'iter' . $j);
                $expected++;
            }

            $this->store->putEvents($streamId, $events);
        }

        $actual = 0;
        $lastStreamId = null;
        $lastIter = 0;

        $receiver = function (Event $event, StreamId $streamId) use (&$actual, &$lastStreamId, &$lastIter) {
            if ($lastStreamId !== $streamId) {
                $lastStreamId = $streamId;
                $lastIter = 0;
            }

            $this->assertSame('iter' . $lastIter, $event->get('name'));
            $actual++;
            $lastIter++;
        };

        $this->store->pipeAllEvents($receiver);
        $this->assertSame($expected, $actual);
    }
}