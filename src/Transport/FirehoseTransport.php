<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Transport;

use Aws\Firehose\FirehoseClient;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\ServiceLocator;

final class FirehoseTransport extends AbstractTransport
{
    private FirehoseClient $client;
    private Router $router;

    public function __construct(ServiceLocator $locator, FirehoseClient $client, Router $router)
    {
        parent::__construct($locator);
        $this->client = $client;
        $this->router = $router;
    }

    protected function doSendCommand(Message $command): void
    {
        $this->putRecord($command, $this->router->forCommand($command));
    }

    protected function doSendEvent(Message $event): void
    {
        $this->putRecord($event, $this->router->forEvent($event));
    }

    /**
     * @param Message $message
     * @param string  $deliveryStreamName
     *
     * @throws \Throwable
     *
     * @see FirehoseClient::putRecord
     */
    protected function putRecord(Message $message, string $deliveryStreamName): void
    {
        $envelope = new TransportEnvelope($message, TransportEnvelope::SERIALIZER_JSON);
        $this->client->putRecord([
            'DeliveryStreamName' => $deliveryStreamName,
            'Record'             => [
                // line break here is VERY important - produces json line delimited records
                // when firehose delivers to s3, es, etc.
                'Data' => $envelope->toString() . PHP_EOL,
            ],
        ]);
    }
}
