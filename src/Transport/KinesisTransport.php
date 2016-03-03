<?php

namespace Gdbots\Pbjx\Transport;

use Aws\Kinesis\KinesisClient;
use Gdbots\Pbj\Serializer\JsonSerializer;
use Gdbots\Pbjx\PartitionableRouter;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Schemas\Pbjx\Command\Command;
use Gdbots\Schemas\Pbjx\Event\Event;

class KinesisTransport extends AbstractTransport
{
    /** @var KinesisClient */
    protected $client;

    /** @var JsonSerializer */
    protected $serializer;

    /** @var PartitionableRouter */
    protected $router;

    /**
     * @param ServiceLocator $locator
     * @param KinesisClient $client
     * @param PartitionableRouter $router
     */
    public function __construct(ServiceLocator $locator, KinesisClient $client, PartitionableRouter $router)
    {
        parent::__construct($locator);
        $this->client = $client;
        $this->router = $router;
        $this->serializer = new JsonSerializer();
    }

    /**
     * @see PartitionableRouter::forCommand
     * @see KinesisClient::putRecord
     *
     * @param Command $command
     * @throws \Exception
     */
    protected function doSendCommand(Command $command)
    {
        $result = $this->client->putRecord([
            'StreamName' => $this->router->forCommand($command),
            'PartitionKey' => $this->router->partitionForCommand($command),
            'Data' => $this->serializer->serialize($command),
        ]);
    }

    /**
     * @see PartitionableRouter::forEvent
     * @see KinesisClient::putRecord
     *
     * @param Event $event
     * @throws \Exception
     */
    protected function doSendEvent(Event $event)
    {
        $this->client->putRecord([
            'StreamName' => $this->router->forEvent($event),
            'PartitionKey' => $this->router->partitionForEvent($event),
            'Data' => $this->serializer->serialize($event),
        ]);
    }
}
