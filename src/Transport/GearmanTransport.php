<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Transport;

use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Mixin\Response\Response;

final class GearmanTransport extends AbstractTransport
{
    /** @var \GearmanClient */
    private $client;

    /** @var Router */
    private $router;

    /**
     * @link http://php.net/manual/en/gearmanclient.addserver.php
     * @var array
     */
    private $servers = [];

    /**
     * @link http://php.net/manual/en/gearmanclient.settimeout.php
     * @var int
     */
    private $timeout = 5000;

    /**
     * Number of reconnects that have occurred.
     *
     * @var int
     */
    private $reconnects = 0;

    /**
     * Maximum number of times a reconnect will be attempted.
     *
     * @var int
     */
    private $maxReconnects = 10;

    /**
     * When these gearman exceptions occur, we'll attempt a reconnect
     * so long as maxReconnects has not been exceeded.
     *
     * @var array
     */
    private static $reconnectCodes = [
        GEARMAN_TIMEOUT           => true,
        GEARMAN_LOST_CONNECTION   => true,
        GEARMAN_COULD_NOT_CONNECT => true,
    ];

    /**
     * @param ServiceLocator $locator
     * @param array          $servers format [['host' => '127.0.0.1', 'port' => 4730]]
     * @param int            $timeout milliseconds
     * @param Router         $router
     * @param int            $maxReconnects
     */
    public function __construct(
        ServiceLocator $locator,
        array $servers = [],
        int $timeout = 100,
        ?Router $router = null,
        int $maxReconnects = 10
    ) {
        parent::__construct($locator);
        $this->servers = $servers;
        $this->timeout = NumberUtils::bound($timeout, 100, 10000);
        $this->router = $router ?: new GearmanRouter();
        $this->maxReconnects = NumberUtils::bound($maxReconnects, 1, 10);
    }

    /**
     * @see Router::forCommand
     * @see GearmanClient::doBackground
     *
     * @param Command $command
     *
     * @throws \Exception
     */
    protected function doSendCommand(Command $command): void
    {
        if (!$this->shouldUseGearman()) {
            $this->locator->getCommandBus()->receiveCommand($command);
            return;
        }

        $envelope = new TransportEnvelope($command, 'php');
        $channel = $this->router->forCommand($command);

        try {
            $client = $this->getClient();
            @$client->doBackground($channel, $envelope->toString(), (string)$command->get('command_id'));
            $this->validateReturnCode($client, $channel);
        } catch (\GearmanException $ge) {
            if (isset(self::$reconnectCodes[$ge->getCode()])) {
                $this->destroyClient();
                $this->locator->getCommandBus()->receiveCommand($command);
                return;
            }

            throw $ge;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @see Router::forEvent
     * @see GearmanClient::doBackground
     *
     * @param Event $event
     *
     * @throws \Exception
     */
    protected function doSendEvent(Event $event): void
    {
        if (!$this->shouldUseGearman()) {
            $this->locator->getEventBus()->receiveEvent($event);
            return;
        }

        $envelope = new TransportEnvelope($event, 'php');
        $channel = $this->router->forEvent($event);

        try {
            $client = $this->getClient();
            @$client->doBackground($channel, $envelope->toString(), (string)$event->get('event_id'));
            $this->validateReturnCode($client, $channel);
        } catch (\GearmanException $ge) {
            if (isset(self::$reconnectCodes[$ge->getCode()])) {
                $this->destroyClient();
                $this->locator->getEventBus()->receiveEvent($event);
                return;
            }

            throw $ge;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Processes the request in memory synchronously.
     *
     * @param Request $request
     *
     * @return Response
     * @throws \Exception
     */
    protected function doSendRequest(Request $request): Response
    {
        if (!$this->shouldUseGearman()) {
            return $this->locator->getRequestBus()->receiveRequest($request);
        }

        $envelope = new TransportEnvelope($request, 'php');
        $channel = $this->router->forRequest($request);

        try {
            $client = $this->getClient();
            $result = @$client->doNormal($channel, $envelope->toString(), (string)$request->get('request_id'));
            $this->validateReturnCode($client, $channel);
            return TransportEnvelope::fromString($result)->getMessage();
        } catch (\GearmanException $ge) {
            if (isset(self::$reconnectCodes[$ge->getCode()])) {
                $this->destroyClient();
                return $this->locator->getRequestBus()->receiveRequest($request);
            }

            throw $ge;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * If the maxReconnects hasn't been exceeded and we still have
     * an active client, then use gearman.
     *
     * @return bool
     */
    private function shouldUseGearman(): bool
    {
        $okay = $this->reconnects < $this->maxReconnects || null !== $this->client;
        echo '$okay = '.($okay ? 'yes' : 'no').PHP_EOL;
        echo '$this->reconnects = '.$this->reconnects.PHP_EOL;
        echo '$this->maxReconnects = '.$this->maxReconnects.PHP_EOL;
        if (!$okay) {
            die ('nope');
        }
        return $this->reconnects < $this->maxReconnects || null !== $this->client;
    }

    /**
     * Destroys the current client and calculates a new timeout for the
     * next client created to be an exponential backoff with jitter,
     * 100ms base, 5 sec ceiling.
     *
     * @return void
     */
    private function destroyClient(): void
    {
        ++$this->reconnects;
        $this->client = null;
        $delay = mt_rand(0, (int)min(5000, (int)pow(2, $this->reconnects) * 100));
        echo '$delay = '.$delay.PHP_EOL;
        usleep($delay * 1000);
    }

    /**
     * Creates a gearman client, sets the timeout and adds the servers.
     * At least one server must connect successfully otherwise an exception is thrown.
     *
     * Gearman's failure to add a server produces an exception with a message of
     * 'Failed to set exception option' which isn't entirely clear but it means you
     * won't be sending any messages.  :)
     *
     * @return \GearmanClient
     *
     * @throws \GearmanException
     */
    private function getClient(): \GearmanClient
    {
        if (null === $this->client) {
            static $i = 0;
            echo 'creating client '.++$i.PHP_EOL;
            //$this->reconnects = 0;

            $client = new \GearmanClient();
            $client->setTimeout($this->timeout);

            if (empty($this->servers)) {
                try {
                    // by default we add the local machine
                    if (!$client->addServer()) {
                        throw new \GearmanException(
                            'GearmanClient::addServer returned false.',
                            GEARMAN_COULD_NOT_CONNECT
                        );
                    }
                } catch (\Exception $e) {
                    throw new \GearmanException(
                        'Unable to add local server 127.0.0.1:4730.  ' . $e->getMessage(),
                        GEARMAN_COULD_NOT_CONNECT
                    );
                }
            } else {
                shuffle($this->servers);
                $added = 0;
                foreach ($this->servers as $server) {
                    $host = isset($server['host']) ? $server['host'] : '127.0.0.1';
                    $port = (int)isset($server['port']) ? $server['port'] : 4730;
                    try {
                        if ($client->addServer($host, $port)) {
                            $added++;
                        }
                    } catch (\Exception $e) {
                        // do nothing, yet.
                    }
                }

                if (0 === $added) {
                    throw new \GearmanException(
                        sprintf('Unable to add any of these servers: %s', json_encode($this->servers)),
                        GEARMAN_COULD_NOT_CONNECT
                    );
                }
            }

            $this->client = $client;
        }

        return $this->client;
    }

    /**
     * Checks the return code from gearman and throws an exception if it's a failure.
     *
     * @param \GearmanClient $client
     * @param string         $channel
     *
     * @throws \Exception
     */
    private function validateReturnCode(\GearmanClient $client, string $channel): void
    {
        switch ($client->returnCode()) {
            case GEARMAN_SUCCESS:
            case GEARMAN_WORK_DATA:
            case GEARMAN_WORK_STATUS:
                return;

            case GEARMAN_TIMEOUT:
                throw new \GearmanException(
                    sprintf(
                        'Code [%s] :: Timeout reached, no available workers for channel [%s].',
                        $client->returnCode(),
                        $channel
                    ), $client->returnCode()
                );

            case GEARMAN_ERRNO:
                throw new \GearmanException(
                    sprintf(
                        'Code [%s] :: Error [%s::%s] on channel [%s].',
                        $client->returnCode(),
                        $client->getErrno(),
                        $client->error(),
                        $channel
                    ), $client->returnCode()
                );

            case GEARMAN_WORK_FAIL:
                throw new \GearmanException(
                    sprintf(
                        'Code [%s] :: Worker failed [%s::%s] on channel [%s].',
                        $client->returnCode(),
                        $client->getErrno(),
                        $client->error(),
                        $channel
                    ), $client->returnCode()
                );

            case GEARMAN_LOST_CONNECTION:
            case GEARMAN_COULD_NOT_CONNECT:
                throw new \GearmanException(
                    sprintf(
                        'Code [%s] :: Lost connection to channel [%s].',
                        $client->returnCode(),
                        $channel
                    ), $client->returnCode()
                );

            default:
                throw new \GearmanException(
                    sprintf(
                        'Code [%s] :: Unspecified error [%s::%s] on channel [%s].',
                        $client->returnCode(),
                        $client->getErrno(),
                        $client->error(),
                        $channel
                    ), $client->returnCode()
                );
        }
    }
}
