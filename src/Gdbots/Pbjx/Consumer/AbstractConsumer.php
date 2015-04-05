<?php

namespace Gdbots\Pbjx\Consumer;

use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\NumberUtils;
use Gdbots\Common\Util\StringUtils;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\Mixin\Command;
use Gdbots\Pbj\Mixin\DomainEvent;
use Gdbots\Pbj\Mixin\Request;
use Gdbots\Pbj\Mixin\Response;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\Pbjx\ServiceLocator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

// todo: implement "replay" logic for messages?
abstract class AbstractConsumer
{
    /* @var bool */
    private $isRunning = false;

    /* @var bool */
    private $boundSignals = false;

    /** @var ServiceLocator */
    protected $locator;

    /** @var LoggerInterface */
    protected $logger;

    /* @var string */
    protected $consumerName;

    /**
     * @param ServiceLocator $locator
     * @param LoggerInterface $logger
     */
    public function __construct(ServiceLocator $locator, LoggerInterface $logger = null)
    {
        $this->locator = $locator;
        $this->logger = $logger ?: new NullLogger();
        $this->consumerName = StringUtils::toSnakeFromCamel(
            str_replace('Consumer', '', ClassUtils::getShortName($this))
        );
    }

    /**
     * Runs the consumer until a signal is caught or the max runtime is reached.
     * @param int $maxRuntime
     */
    final public function run($maxRuntime = 300)
    {
        $maxRuntime = NumberUtils::bound($maxRuntime, 10, 86400);
        $start = time();
        $this->logger->notice(
            sprintf('Starting [%s] consumer, will run for up to [%d] seconds.', $this->consumerName, $maxRuntime)
        );
        $this->setup();

        $this->isRunning = true;
        $this->bindSignals();
        $dispatcher = $this->locator->getDispatcher();

        do {
            try {
                $this->work();
            } catch(\Exception $e) {
                $this->stop();
                $this->logger->critical(
                    sprintf(
                        'Consumer [%s] caught an exception with message [%s], shutting down.',
                        $this->consumerName,
                        $e->getMessage()
                    )
                );
                $dispatcher->dispatch(PbjxEvents::CONSUMER_HANDLING_EXCEPTION);
                break;
            }

            if ($this->isRunning && $maxRuntime > 0 && time() - $start > $maxRuntime) {
                $this->logger->notice(
                    sprintf(
                        'Consumer [%s] has been running for more than [%d] seconds, shutting down.',
                        $this->consumerName,
                        $maxRuntime
                    )
                );
                $this->stop();
            }
        } while ($this->isRunning());

        $this->teardown();
        $dispatcher->dispatch(PbjxEvents::CONSUMER_AFTER_TEARDOWN);
        $this->stop();
    }

    /**
     * Stops the consumer.  This allows it to finish its current work
     * and perform a clean shutdown if possible.
     */
    final public function stop()
    {
        $this->isRunning = false;
    }

    /**
     * @return bool
     */
    final public function isRunning()
    {
        return $this->isRunning;
    }

    /**
     * This runs prior to the run loop starting.
     */
    protected function setup()
    {
        // override for custom setup.
    }

    /**
     * Override in the concrete consumer to perform the actual work.
     * This is called repeatly in a while loop when the consumer is running.
     */
    protected function work()
    {
        // concrete consumer should do something.
    }

    /**
     * This runs after to the run loop stops.
     */
    protected function teardown()
    {
        // override for custom teardown.
    }

    /**
     * @param Message $message
     * @return Message|null
     */
    final protected function handleMessage(Message $message)
    {
        if ($message instanceof Command) {
            $this->handleCommand($message);
            return null;
        }

        if ($message instanceof DomainEvent) {
            $this->handleEvent($message);
            return null;
        }

        if ($message instanceof Request) {
            return $this->handleRequest($message);
        }
    }

    /**
     * @param Command $command
     */
    final protected function handleCommand(Command $command)
    {
        $this->locator->getCommandBus()->receiveCommand($command);
    }

    /**
     * @param DomainEvent $domainEvent
     */
    final protected function handleEvent(DomainEvent $domainEvent)
    {
        $this->locator->getEventBus()->receiveEvent($domainEvent);
    }

    /**
     * @param Request $request
     * @return Response
     */
    final protected function handleRequest(Request $request)
    {
        return $this->locator->getRequestBus()->receiveRequest($request);
    }

    /**
     * Binds this consumer to signals from the terminal.
     */
    private function bindSignals()
    {
        if ($this->boundSignals) {
            return;
        }

        $this->boundSignals = true;
        declare(ticks = 1);

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGCHLD, SIG_IGN); // Zombies
            pcntl_signal(SIGTERM, array($this, 'handleSignals')); // Kill
            pcntl_signal(SIGINT, array($this, 'handleSignals')); // Control + C (from shell)
        }
    }

    /**
     * @param int $signo
     */
    private function handleSignals($signo)
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
