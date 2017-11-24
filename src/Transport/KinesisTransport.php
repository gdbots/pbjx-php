<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Transport;

use Aws\Kinesis\KinesisClient;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;

final class KinesisTransport extends AbstractTransport
{
    /** @var KinesisClient */
    private $client;

    /** @var PartitionableRouter */
    private $router;

    /**
     * @param ServiceLocator      $locator
     * @param KinesisClient       $client
     * @param PartitionableRouter $router
     */
    public function __construct(ServiceLocator $locator, KinesisClient $client, PartitionableRouter $router)
    {
        parent::__construct($locator);
        $this->client = $client;
        $this->router = $router;
    }

    /**
     * @see PartitionableRouter::forCommand
     * @see KinesisClient::putRecord
     *
     * @param Command $command
     *
     * @throws \Exception
     */
    protected function doSendCommand(Command $command): void
    {
        $envelope = new TransportEnvelope($command, 'json');
        $this->client->putRecord([
            'StreamName'   => $this->router->forCommand($command),
            'PartitionKey' => $this->router->partitionForCommand($command),
            'Data'         => $envelope->toString(),
        ]);
    }

    /**
     * @see PartitionableRouter::forEvent
     * @see KinesisClient::putRecord
     *
     * @param Event $event
     *
     * @throws \Exception
     */
    protected function doSendEvent(Event $event): void
    {
        $envelope = new TransportEnvelope($event, 'json');
        $this->client->putRecord([
            'StreamName'   => $this->router->forEvent($event),
            'PartitionKey' => $this->router->partitionForEvent($event),
            'Data'         => $envelope->toString(),
        ]);
    }
}
