<?php

namespace Gdbots\Pbjx\Consumer;

use Gdbots\Pbj\Serializer\JsonSerializer;
use Gdbots\Pbjx\ServiceLocator;
use Psr\Log\LoggerInterface;

/**
 * KinesisConsumer is run by the Amazon Kinesis Client MultiLangDaemon
 * @link https://github.com/awslabs/amazon-kinesis-client
 * @link https://github.com/awslabs/amazon-kinesis-client/blob/master/src/main/java/com/amazonaws/services/kinesis/multilang/MultiLangDaemon.java
 */
class KinesisConsumer extends AbstractConsumer
{
    /** @var JsonSerializer */
    protected $serializer;

    /**
     * @param ServiceLocator $locator
     * @param LoggerInterface $logger
     */
    public function __construct(ServiceLocator $locator, LoggerInterface $logger = null)
    {
        parent::__construct($locator, $logger);
        $this->serializer = new JsonSerializer();
    }

    /**
     * Reads from STDIN and processes records.
     */
    protected function work()
    {
        $data = fgets(STDIN);
        error_log($data);
        // todo: write the multilangdaemon handlers
        sleep(3);
    }
}
