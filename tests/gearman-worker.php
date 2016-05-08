<?php

include 'bootstrap.php';

use Gdbots\Pbjx\Consumer\GearmanConsumer;
use Gdbots\Pbjx\RegisteringServiceLocator;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequest;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequestHandler;
use Gdbots\Tests\Pbjx\Fixtures\SayHello;
use Gdbots\Tests\Pbjx\Fixtures\SayHelloHandler;
use Gdbots\Tests\Pbjx\Fixtures\SimpleEvent;

$locator = new RegisteringServiceLocator();
$locator->registerCommandHandler(SayHello::schema()->getCurie(), new SayHelloHandler());
$locator->registerRequestHandler(GetTimeRequest::schema()->getCurie(), new GetTimeRequestHandler());

$locator->getDispatcher()->addListener(
    SimpleEvent::schema()->getCurieMajor(),
    function (SimpleEvent $publishedEvent) {
        echo $publishedEvent;
        echo $publishedEvent->isReplay() ? 'replayed' . PHP_EOL : '';
    }
);

$consumer = new GearmanConsumer($locator, 'gdbots_pbjx_test');
$consumer->run();
