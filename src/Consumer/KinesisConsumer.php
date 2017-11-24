<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Consumer;

use Gdbots\Pbjx\Exception\LogicException;
use Gdbots\Pbjx\ServiceLocator;
use Psr\Log\LoggerInterface;

/**
 * KinesisConsumer is run by the Amazon Kinesis Client MultiLangDaemon
 * @link https://github.com/awslabs/amazon-kinesis-client
 * @link https://github.com/awslabs/amazon-kinesis-client/blob/master/src/main/java/com/amazonaws/services/kinesis/multilang/MultiLangDaemon.java
 */
final class KinesisConsumer extends AbstractConsumer
{
    /**
     * @param ServiceLocator  $locator
     * @param LoggerInterface $logger
     */
    public function __construct(ServiceLocator $locator, ?LoggerInterface $logger = null)
    {
        parent::__construct($locator, $logger);
    }

    /**
     * Reads from STDIN and processes records.
     * todo: write the multilangdaemon handlers
     */
    protected function work(): void
    {
        throw new LogicException(__CLASS__ . '::' . __FUNCTION__ . ' is not implemented yet.');
        /*
        $data = fgets(STDIN);
        error_log($data);
        sleep(3);
        */
    }
}
