<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Transport;

use Aws\Kinesis\KinesisClient;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\ServiceLocator;

final class KinesisTransport extends AbstractTransport
{
    private KinesisClient $client;
    private PartitionableRouter $router;

    public function __construct(ServiceLocator $locator, KinesisClient $client, PartitionableRouter $router)
    {
        parent::__construct($locator);
        $this->client = $client;
        $this->router = $router;
    }

    protected function doSendCommand(Message $command): void
    {
        $this->putRecord($command, $this->router->forCommand($command), $this->router->partitionForCommand($command));
    }

    protected function doSendEvent(Message $event): void
    {
        $this->putRecord($event, $this->router->forEvent($event), $this->router->partitionForEvent($event));
    }

    /**
     * @param Message $message
     * @param string  $streamName
     * @param string  $partitionKey
     *
     * @throws \Throwable
     *
     * @see KinesisClient::putRecord
     */
    protected function putRecord(Message $message, string $streamName, string $partitionKey): void
    {
        $envelope = new TransportEnvelope($message, TransportEnvelope::SERIALIZER_JSON);
        $this->client->putRecord([
            'StreamName'   => $streamName,
            'PartitionKey' => $partitionKey,
            'Data'         => $envelope->toString(),
        ]);
    }
}
