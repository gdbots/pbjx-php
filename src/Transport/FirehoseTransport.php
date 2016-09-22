<?php

namespace Gdbots\Pbjx\Transport;

use Aws\Firehose\FirehoseClient;
use Aws\Result;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;

class FirehoseTransport extends AbstractTransport
{
    /** @var FirehoseClient */
    protected $client;

    /** @var Router */
    protected $router;

    /**
     * @param ServiceLocator $locator
     * @param FirehoseClient $client
     * @param Router $router
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
     * @throws \Exception
     */
    protected function doSendCommand(Command $command)
    {
        $envelope = new TransportEnvelope($command, 'json');
        $result = $this->client->putRecord([
            'DeliveryStreamName' => $this->router->forCommand($command),
            'Record' => [
                // line break here is VERY important - produces json line delimited records
                // when firehose delivers to s3, es, etc.
                'Data' => $envelope->toString() . PHP_EOL
            ],
        ]);

        $this->afterSendCommand($envelope, $result);
    }

    /**
     * @param TransportEnvelope $envelope
     * @param Result $result
     */
    protected function afterSendCommand(TransportEnvelope $envelope, Result $result)
    {
        // override to log or process aws result
    }

    /**
     * @see FirehoseClient::putRecord
     *
     * @param Event $event
     * @throws \Exception
     */
    protected function doSendEvent(Event $event)
    {
        $envelope = new TransportEnvelope($event, 'json');
        $result = $this->client->putRecord([
            'DeliveryStreamName' => $this->router->forEvent($event),
            'Record' => [
                // line break here is VERY important - produces json line delimited records
                // when firehose delivers to s3, es, etc.
                'Data' => $envelope->toString() . PHP_EOL
            ],
        ]);

        $this->afterSendEvent($envelope, $result);
    }

    /**
     * @param TransportEnvelope $envelope
     * @param Result $result
     */
    protected function afterSendEvent(TransportEnvelope $envelope, Result $result)
    {
        // override to log or process aws result
    }
}
