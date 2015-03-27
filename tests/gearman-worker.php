<?php

include 'bootstrap.php';

use Gdbots\Pbjx\Consumer\GearmanConsumer;
use Gdbots\Pbjx\RegisteringServiceLocator;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequest;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequestHandler;
use Gdbots\Tests\Pbjx\Fixtures\SayHello;
use Gdbots\Tests\Pbjx\Fixtures\SayHelloHandler;

$locator = new RegisteringServiceLocator();
$locator->registerCommandHandler(SayHello::schema()->getCurie(), new SayHelloHandler());
$locator->registerRequestHandler(GetTimeRequest::schema()->getCurie(), new GetTimeRequestHandler());

$consumer = new GearmanConsumer($locator);
$consumer->run();

