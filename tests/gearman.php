<?php

include 'bootstrap.php';

use Gdbots\Pbjx\RegisteringServiceLocator;
use Gdbots\Pbjx\Transport\GearmanTransport;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequest;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequestHandler;
use Gdbots\Tests\Pbjx\Fixtures\SayHello;
use Gdbots\Tests\Pbjx\Fixtures\SimpleEvent;

$locator = new RegisteringServiceLocator();
$locator->setDefaultTransport(new GearmanTransport($locator));
$locator->registerRequestHandler(GetTimeRequest::schema()->getCurie(), new GetTimeRequestHandler());
$pbjx = $locator->getPbjx();

while (true) {
    $time = time();

    $command = SayHello::create()->setName('marge :: ' . $time);
    $pbjx->send($command);
    echo 'Sent -> ' . $command->getCommandId() . PHP_EOL;
    echo $command . PHP_EOL . PHP_EOL;

    $event = SimpleEvent::create()->setName('homer :: ' . $time);
    $pbjx->publish($event);
    echo 'Published -> ' . $event->getEventId() . PHP_EOL;
    echo $event . PHP_EOL . PHP_EOL;

    $request = GetTimeRequest::create();
    echo 'Requested -> ' . $request->getRequestId() . PHP_EOL;
    try {
        $response = $pbjx->request($request);
        echo $response . PHP_EOL . PHP_EOL;
    } catch (\Exception $e) {
        echo $e->getMessage() . PHP_EOL . PHP_EOL;
    }

    usleep(25000);
}
