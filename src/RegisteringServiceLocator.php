<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\EventStore\EventStore;
use Gdbots\Pbjx\Exception\HandlerNotFound;
use Gdbots\Pbjx\Transport\Transport;

/**
 * This service locator requires that you register the handlers
 * for all commands/requests.  Not ideal for large apps but
 * convenient and simple for test cases and very small apps.
 *
 * In most cases you'll use a container aware service locator.
 */
final class RegisteringServiceLocator extends AbstractServiceLocator
{
    private $handlers = [];

    /**
     * {@inheritdoc}
     */
    public function getCommandHandler(SchemaCurie $curie): CommandHandler
    {
        if (isset($this->handlers[$curie->toString()])) {
            return $this->handlers[$curie->toString()];
        }

        throw new HandlerNotFound($curie);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestHandler(SchemaCurie $curie): RequestHandler
    {
        if (isset($this->handlers[$curie->toString()])) {
            return $this->handlers[$curie->toString()];
        }

        throw new HandlerNotFound($curie);
    }

    /**
     * @param SchemaCurie    $curie
     * @param CommandHandler $handler
     */
    public function registerCommandHandler(SchemaCurie $curie, CommandHandler $handler): void
    {
        $this->handlers[$curie->toString()] = $handler;
    }

    /**
     * @param SchemaCurie    $curie
     * @param RequestHandler $handler
     */
    public function registerRequestHandler(SchemaCurie $curie, RequestHandler $handler): void
    {
        $this->handlers[$curie->toString()] = $handler;
    }

    /**
     * @param Transport $transport
     */
    public function setDefaultTransport(Transport $transport): void
    {
        $this->defaultTransport = $transport;
    }

    /**
     * @param EventStore $eventStore
     */
    public function setEventStore(EventStore $eventStore): void
    {
        $this->eventStore = $eventStore;
    }

    /**
     * @param EventSearch $eventSearch
     */
    public function setEventSearch(EventSearch $eventSearch): void
    {
        $this->eventSearch = $eventSearch;
    }
}
