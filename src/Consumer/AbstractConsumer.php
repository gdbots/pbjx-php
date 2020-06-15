<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Consumer;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\ClassUtil;
use Gdbots\Pbj\Util\NumberUtil;
use Gdbots\Pbj\Util\StringUtil;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Pbjx\ServiceLocator;
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Request\RequestV1Mixin;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractConsumer
{
    protected ServiceLocator $locator;
    protected LoggerInterface $logger;
    protected string $consumerName;
    private bool $isRunning = false;

    public function __construct(ServiceLocator $locator, ?LoggerInterface $logger = null)
    {
        $this->locator = $locator;
        $this->logger = $logger ?: new NullLogger();
        $this->consumerName = StringUtil::toSnakeFromCamel(
            str_replace('Consumer', '', ClassUtil::getShortName($this))
        );
    }

    /**
     * Runs the consumer until a signal is caught or the max runtime is reached.
     *
     * @param int $maxRuntime
     */
    final public function run(int $maxRuntime = 300): void
    {
        if ($this->isRunning) {
            $this->logger->notice(sprintf('Consumer [%s] is already running.', $this->consumerName));
            return;
        }

        /*
         * In many distributed systems multiple consumers are created and these often get
         * started at the exact same time by something like upstart.  Randomizing start
         * times helps with processes depending on live consumers (like gearman) that
         * come and go.  Ensuring they don't come and go all close together makes it less
         * likely that no service will be available while consumers restart.
         */
        $jitter = mt_rand(0, 3000);
        usleep($jitter * 1000);

        $maxRuntime += mt_rand(0, 20);
        $maxRuntime = NumberUtil::bound($maxRuntime, 10, 86400);
        $start = time();
        $this->logger->notice(
            sprintf('Starting [%s] consumer, will run for up to [%d] seconds.', $this->consumerName, $maxRuntime)
        );
        $this->setup();

        $this->isRunning = true;
        declare(ticks=1);
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGCHLD, SIG_IGN); // Zombies
            pcntl_signal(SIGTERM, [$this, 'handleSignals']); // Kill
            pcntl_signal(SIGINT, [$this, 'handleSignals']); // Control + C (from shell)
        }
        $dispatcher = $this->locator->getDispatcher();

        do {
            try {
                $this->work();
            } catch (\Throwable $e) {
                $this->stop();
                $this->logger->critical(
                    sprintf(
                        '%s::Consumer [%s] caught an exception, shutting down.',
                        ClassUtil::getShortName($e),
                        $this->consumerName
                    ),
                    [
                        'exception' => $e,
                        'consumer'  => $this->consumerName,
                    ]
                );

                $dispatcher->dispatch(new Event(), PbjxEvents::CONSUMER_HANDLING_EXCEPTION);
                break;
            }

            if ($this->isRunning && $maxRuntime > 0 && time() - $start > $maxRuntime) {
                $this->stop();
                $this->logger->notice(
                    sprintf(
                        'Consumer [%s] has been running for more than [%d] seconds, shutting down.',
                        $this->consumerName,
                        $maxRuntime
                    )
                );
                break;
            }
        } while ($this->isRunning());

        $this->teardown();
        $dispatcher->dispatch(new Event(), PbjxEvents::CONSUMER_AFTER_TEARDOWN);
        $this->stop();
    }

    /**
     * Stops the consumer.  This allows it to finish its current work
     * and perform a clean shutdown if possible.
     */
    final public function stop(): void
    {
        $this->isRunning = false;
    }

    final public function isRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * This runs prior to the run loop starting.
     */
    protected function setup(): void
    {
        // override for custom setup.
    }

    /**
     * Override in the concrete consumer to perform the actual work.
     * This is called repeatly in a while loop when the consumer is running.
     */
    protected function work(): void
    {
        // concrete consumer should do something.
    }

    /**
     * This runs after to the run loop stops.
     */
    protected function teardown(): void
    {
        // override for custom teardown.
    }

    final protected function handleMessage(Message $message): ?Message
    {
        if ($message::schema()->hasMixin(CommandV1Mixin::SCHEMA_CURIE)) {
            $this->handleCommand($message);
            return null;
        }

        if ($message::schema()->hasMixin(EventV1Mixin::SCHEMA_CURIE)) {
            $this->handleEvent($message);
            return null;
        }

        if ($message::schema()->hasMixin(RequestV1Mixin::SCHEMA_CURIE)) {
            return $this->handleRequest($message);
        }
    }

    private function handleCommand(Message $command): void
    {
        $this->locator->getCommandBus()->receiveCommand($command);
    }

    private function handleEvent(Message $event): void
    {
        $this->locator->getEventBus()->receiveEvent($event);
    }

    private function handleRequest(Message $request): Message
    {
        return $this->locator->getRequestBus()->receiveRequest($request);
    }

    final public function handleSignals(int $signo, $signinfo = null): void
    {
        switch ($signo) {
            case SIGINT:
            case SIGTERM:
                $this->stop();
                $this->logger->notice(
                    sprintf('Caught signal [%s], shutting down [%s]...', $signo, $this->consumerName)
                );
                break;
        }
    }
}
