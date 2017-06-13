<?php
declare(strict_types = 1);

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
     * @param ServiceLocator $locator
     * @param array          $servers format [['host' => '127.0.0.1', 'port' => 4730]]
     * @param int            $timeout milliseconds
     * @param Router         $router
     */
    public function __construct(ServiceLocator $locator, array $servers = [], int $timeout = 5000, ?Router $router = null)
    {
        parent::__construct($locator);
        $this->servers = $servers;
        $this->timeout = NumberUtils::bound($timeout, 200, 10000);
        $this->router = $router ?: new GearmanRouter();
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
        $envelope = new TransportEnvelope($command, 'php');
        $channel = $this->router->forCommand($command);

        try {
            $client = $this->getClient();
            @$client->doBackground($channel, $envelope->toString(), (string)$command->get('command_id'));
            $this->validateReturnCode($client, $channel);
        } catch (\GearmanException $ge) {
            switch ($ge->getCode()) {
                case GEARMAN_TIMEOUT:
                case GEARMAN_LOST_CONNECTION:
                case GEARMAN_COULD_NOT_CONNECT:
                    $this->client = null;
                    $this->timeout = 50;
                    $this->locator->getCommandBus()->receiveCommand($command);
                    break;

                default:
                    throw $ge;
            }
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
        $envelope = new TransportEnvelope($event, 'php');
        $channel = $this->router->forEvent($event);

        try {
            $client = $this->getClient();
            @$client->doBackground($channel, $envelope->toString(), (string)$event->get('event_id'));
            $this->validateReturnCode($client, $channel);
        } catch (\GearmanException $ge) {
            switch ($ge->getCode()) {
                case GEARMAN_TIMEOUT:
                case GEARMAN_LOST_CONNECTION:
                case GEARMAN_COULD_NOT_CONNECT:
                    $this->client = null;
                    $this->timeout = 50;
                    $this->locator->getEventBus()->receiveEvent($event);
                    break;

                default:
                    throw $ge;
            }
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
        $envelope = new TransportEnvelope($request, 'php');
        $channel = $this->router->forRequest($request);

        try {
            $client = $this->getClient();
            $result = @$client->doNormal($channel, $envelope->toString(), (string)$request->get('request_id'));
            $this->validateReturnCode($client, $channel);
            return TransportEnvelope::fromString($result)->getMessage();
        } catch (\GearmanException $ge) {
            switch ($ge->getCode()) {
                case GEARMAN_TIMEOUT:
                case GEARMAN_LOST_CONNECTION:
                case GEARMAN_COULD_NOT_CONNECT:
                    $this->client = null;
                    $this->timeout = 50;
                    return $this->locator->getRequestBus()->receiveRequest($request);

                default:
                    throw $ge;
            }
        } catch (\Exception $e) {
            throw $e;
        }
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
