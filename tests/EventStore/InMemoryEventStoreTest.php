<?php
declare(strict_types=1);

namespace Gdbots\Tests\Pbjx\EventStore;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\Pbjx\EventStore\InMemoryEventStore;
use Gdbots\Pbjx\Exception\EventNotFound;
use Gdbots\Pbjx\Pbjx;
use Gdbots\Pbjx\RegisteringServiceLocator;
use Gdbots\Schemas\Pbjx\Event\HealthCheckedV1;
use Gdbots\Schemas\Pbjx\StreamId;
use PHPUnit\Framework\TestCase;

class InMemoryEventStoreTest extends TestCase
{
    protected ?RegisteringServiceLocator $locator = null;
    protected ?Pbjx $pbjx = null;
    protected ?InMemoryEventStore $store = null;

    protected function setUp(): void
    {
        $this->locator = new RegisteringServiceLocator();
        $this->pbjx = $this->locator->getPbjx();
        $this->store = new InMemoryEventStore($this->pbjx);
        $this->locator->setEventStore($this->store);
    }

    public function testGetStreamSlice(): void
    {
        $streamId = StreamId::fromString('acme:test');
        $store = $this->pbjx->getEventStore();
        $since = Microtime::create();

        $store->putEvents($streamId, [
            HealthCheckedV1::fromArray([
                HealthCheckedV1::OCCURRED_AT_FIELD => '1489129155504330',
                HealthCheckedV1::MSG_FIELD         => 'past event',
            ]),

            HealthCheckedV1::fromArray([
                HealthCheckedV1::OCCURRED_AT_FIELD => '2489129155504330',
                HealthCheckedV1::MSG_FIELD         => 'future event',
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

    public function testGetEvent(): void
    {
        $streamId = StreamId::fromString('acme:test');
        $store = $this->pbjx->getEventStore();

        $expectedEvents = [
            HealthCheckedV1::fromArray([
                HealthCheckedV1::OCCURRED_AT_FIELD => '1489129155504330',
                HealthCheckedV1::MSG_FIELD         => 'past event',
            ]),

            HealthCheckedV1::fromArray([
                HealthCheckedV1::OCCURRED_AT_FIELD => '2489129155504330',
                HealthCheckedV1::MSG_FIELD         => 'future event',
            ]),
        ];
        $store->putEvents($streamId, $expectedEvents);

        $actualEvent = $store->getEvent($expectedEvents[0]->get(HealthCheckedV1::EVENT_ID_FIELD));
        $this->assertTrue($expectedEvents[0]->equals($actualEvent));

        $actualEvent = $store->getEvent($expectedEvents[1]->get(HealthCheckedV1::EVENT_ID_FIELD));
        $this->assertTrue($expectedEvents[1]->equals($actualEvent));
    }

    public function testGetEvent2(): void
    {
        $streamId = StreamId::fromString('acme:test');
        $store = $this->pbjx->getEventStore();

        $expectedEvents = [
            HealthCheckedV1::fromArray([
                HealthCheckedV1::OCCURRED_AT_FIELD => '1489129155504330',
                HealthCheckedV1::MSG_FIELD         => 'past event',
            ]),

            HealthCheckedV1::fromArray([
                HealthCheckedV1::OCCURRED_AT_FIELD => '2489129155504330',
                HealthCheckedV1::MSG_FIELD         => 'future event',
            ]),
        ];
        $store->putEvents($streamId, $expectedEvents);

        $eventIds = [
            $expectedEvents[0]->get(HealthCheckedV1::EVENT_ID_FIELD),
            $expectedEvents[1]->get(HealthCheckedV1::EVENT_ID_FIELD),
        ];

        $actualEvents = array_values($store->getEvents($eventIds));
        $this->assertTrue($expectedEvents[0]->equals($actualEvents[0]));
        $this->assertTrue($expectedEvents[1]->equals($actualEvents[1]));
    }

    public function testDeleteEvent(): void
    {
        $this->expectException(EventNotFound::class);
        $streamId = StreamId::fromString('acme:test');
        $store = $this->pbjx->getEventStore();

        $expectedEvent = HealthCheckedV1::fromArray([
            HealthCheckedV1::OCCURRED_AT_FIELD => '1489129155504330',
            HealthCheckedV1::MSG_FIELD         => 'past event',
        ]);
        $store->putEvents($streamId, [$expectedEvent]);

        $store->deleteEvent($expectedEvent->get(HealthCheckedV1::EVENT_ID_FIELD));
        $store->getEvent($expectedEvent->get(HealthCheckedV1::EVENT_ID_FIELD));
    }

    public function testPutEvents(): void
    {
        $streamId = StreamId::fromString('acme:test.put');
        $start = Microtime::create();
        $events = [];

        for ($i = 0; $i < 10; $i++) {
            $events[] = HealthCheckedV1::create()->set(HealthCheckedV1::MSG_FIELD, 'iter' . $i);
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

    public function testPipeEvents(): void
    {
        $streamId = StreamId::fromString('acme:test.pipe');
        $events = [];

        for ($i = 0; $i < 100; $i++) {
            $events['iter' . $i] = HealthCheckedV1::create()->set(HealthCheckedV1::MSG_FIELD, 'iter' . $i);
        }

        $this->store->putEvents($streamId, $events);
        foreach ($this->store->pipeEvents($streamId) as $event) {
            unset($events[$event->get(HealthCheckedV1::MSG_FIELD)]);
        };
        $this->assertEmpty($events);
    }

    public function testPipeAllEvents(): void
    {
        $this->store->clear();
        $expected = 0;

        for ($i = 0; $i < 10; $i++) {
            $streamId = StreamId::fromString('acme:test.pipe-all:' . $i);
            $events = [];

            for ($j = 0; $j < 100; $j++) {
                $events[] = HealthCheckedV1::create()->set(HealthCheckedV1::MSG_FIELD, 'iter' . $j);
                $expected++;
            }

            $this->store->putEvents($streamId, $events);
        }

        $actual = 0;
        $lastStreamId = null;
        $lastIter = 0;

        /**
         * @var Message  $event
         * @var StreamId $streamId
         */
        foreach ($this->store->pipeAllEvents() as [$event, $streamId]) {
            if ($lastStreamId !== $streamId) {
                $lastStreamId = $streamId;
                $lastIter = 0;
            }

            $this->assertSame('iter' . $lastIter, $event->get(HealthCheckedV1::MSG_FIELD));
            $actual++;
            $lastIter++;
        }

        $this->assertSame($expected, $actual);
    }
}
