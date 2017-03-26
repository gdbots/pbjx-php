<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx\Transport;

use Aws\Firehose\FirehoseClient;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;

final class FirehoseTransport extends AbstractTransport
{
    /** @var FirehoseClient */
    private $client;

    /** @var Router */
    private $router;

    /**
     * @param ServiceLocator $locator
     * @param FirehoseClient $client
     * @param Router         $router
     */
    public function __construct(ServiceLocator $locator, FirehoseClient $client, Router $router)
    {
        parent::__construct($locator);
        $this->client = $client;
        $this->router = $router;
    }

    /**
     * @see FirehoseClient::putRecord
     *
     * @param Command $command
     *
     * @throws \Exception
     */
    protected function doSendCommand(Command $command): void
    {
        $envelope = new TransportEnvelope($command, 'json');
        $this->client->putRecord([
            'DeliveryStreamName' => $this->router->forCommand($command),
            'Record'             => [
                // line break here is VERY important - produces json line delimited records
                // when firehose delivers to s3, es, etc.
                'Data' => $envelope->toString() . PHP_EOL,
            ],
        ]);
    }

    /**
     * @see FirehoseClient::putRecord
     *
     * @param Event $event
     *
     * @throws \Exception
     */
    protected function doSendEvent(Event $event): void
    {
        $envelope = new TransportEnvelope($event, 'json');
        $this->client->putRecord([
            'DeliveryStreamName' => $this->router->forEvent($event),
            'Record'             => [
                // line break here is VERY important - produces json line delimited records
                // when firehose delivers to s3, es, etc.
                'Data' => $envelope->toString() . PHP_EOL,
            ],
        ]);
    }
}
