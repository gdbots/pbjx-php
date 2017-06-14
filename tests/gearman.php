<?php

include 'bootstrap.php';

use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Pbjx\RegisteringServiceLocator;
use Gdbots\Pbjx\Transport\GearmanTransport;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequest;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequestHandler;
use Gdbots\Tests\Pbjx\Fixtures\SayHello;
use Gdbots\Tests\Pbjx\Fixtures\SayHelloHandler;
use Gdbots\Tests\Pbjx\Fixtures\SimpleEvent;

$locator = new RegisteringServiceLocator();
$locator->setDefaultTransport(new GearmanTransport($locator));
$locator->registerCommandHandler(SayHello::schema()->getCurie(), new SayHelloHandler());
$locator->registerRequestHandler(GetTimeRequest::schema()->getCurie(), new GetTimeRequestHandler());
$pbjx = $locator->getPbjx();

$locator->getDispatcher()->addListener(
    SimpleEvent::schema()->getCurieMajor() . '.'. PbjxEvents::SUFFIX_ENRICH,
    function (PbjxEvent $event, $eventName) {
        echo $eventName . PHP_EOL;
    }
);

while (true) {
    $time = time();

    $command = SayHello::create()->set('name', 'marge :: ' . $time);
    $pbjx->send($command);
    echo 'Sent -> ' . $command->get('command_id') . PHP_EOL;
    echo $command . PHP_EOL . PHP_EOL;

    $event = SimpleEvent::create()->set('name', 'homer :: ' . $time);

    $pbjx->publish($event);
    echo 'Enriched, then Published -> ' . $event->get('event_id') . PHP_EOL;
    echo $event . PHP_EOL . PHP_EOL;

    $event = clone $event;
    $event->clear('event_id');
    $event->isReplay(true);
    $pbjx->publish($event);
    echo 'Replayed Event Published -> ' . $event->get('event_id') . PHP_EOL;
    echo $event . PHP_EOL . PHP_EOL;

    $request = GetTimeRequest::create();
    echo 'Requested -> ' . $request->get('request_id') . PHP_EOL;
    try {
        $response = $pbjx->request($request);
        echo $response . PHP_EOL . PHP_EOL;
    } catch (\Exception $e) {
        echo $e->getMessage() . PHP_EOL . PHP_EOL;
    }

    usleep(25000);
}
