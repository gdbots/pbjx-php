<?php

include 'bootstrap.php';

use Gdbots\Pbj\Mixin\Command;
use Gdbots\Pbj\Mixin\DomainEvent;
use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbj\Serializer\PhpSerializer;
use Gdbots\Pbjx\RegisteringServiceLocator;
use Gdbots\Pbjx\Transport\GearmanTransport;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequest;
use Gdbots\Tests\Pbjx\Fixtures\GetTimeRequestHandler;
use Gdbots\Tests\Pbjx\Fixtures\SayHello;
use Gdbots\Tests\Pbjx\Fixtures\SayHelloHandler;

$locator = new RegisteringServiceLocator();
$locator->setDefaultTransport(new GearmanTransport($locator));
$locator->registerCommandHandler(SayHello::schema()->getCurie(), new SayHelloHandler());
$locator->registerRequestHandler(GetTimeRequest::schema()->getCurie(), new GetTimeRequestHandler());
$serializer = new PhpSerializer();

function work(GearmanJob $job) {
    global $locator, $serializer;

    $message = $serializer->deserialize($job->workload());
    echo sprintf('Received [%s] with id [%s].', $job->handle(), $job->unique()) . PHP_EOL;
    echo $message . PHP_EOL . PHP_EOL;

    if ($message instanceof Command) {
        $locator->getCommandBus()->receiveCommand($message);
        return;
    }

    if ($message instanceof DomainEvent) {
        $locator->getEventBus()->receiveEvent($message);
        return;
    }

    if ($message instanceof Request) {
        return $serializer->serialize($locator->getRequestBus()->receiveRequest($message));
    }
}

$worker = new GearmanWorker();
$worker->addServer();
$worker->addFunction('commands', 'work');
$worker->addFunction('events', 'work');
$worker->addFunction('requests', 'work');

while (true) {
    print 'Waiting for job...' . PHP_EOL;
    $worker->work();
    if ($worker->returnCode() != GEARMAN_SUCCESS) {
        break;
    }
}
