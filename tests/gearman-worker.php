<?php

include 'bootstrap.php';

use Gdbots\Pbj\Serializer\PhpSerializer;

$serializer = new PhpSerializer();

$worker = new GearmanWorker();
$worker->addServer();
$worker->addFunction('commands', 'work');
$worker->addFunction('events', 'work');
$worker->addFunction('requests', 'work');

while (1) {
    print "Waiting for job...\n";
    $ret = $worker->work();
    if ($worker->returnCode() != GEARMAN_SUCCESS) {
        break;
    }
}

function work(GearmanJob $job) {
    global $serializer;
    $workload = $serializer->deserialize($job->workload());
    echo sprintf('Received job [%s] with id [%s].', $job->handle(), $job->unique()) . PHP_EOL;
    echo $workload . PHP_EOL;
}
