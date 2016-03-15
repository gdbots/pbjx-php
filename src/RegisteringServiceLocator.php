<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\Exception\HandlerNotFound;

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
    public function getCommandHandler(SchemaCurie $curie)
    {
        if (isset($this->handlers[$curie->toString()])) {
            return $this->handlers[$curie->toString()];
        }

        throw new HandlerNotFound($curie);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestHandler(SchemaCurie $curie)
    {
        if (isset($this->handlers[$curie->toString()])) {
            return $this->handlers[$curie->toString()];
        }

        throw new HandlerNotFound($curie);
    }

    /**
     * @param SchemaCurie $curie
     * @param CommandHandler $handler
     */
    public function registerCommandHandler(SchemaCurie $curie, CommandHandler $handler)
    {
        $this->handlers[$curie->toString()] = $handler;
    }

    /**
     * @param SchemaCurie $curie
     * @param RequestHandler $handler
     */
    public function registerRequestHandler(SchemaCurie $curie, RequestHandler $handler)
    {
        $this->handlers[$curie->toString()] = $handler;
    }

    /**
     * @param Transport $transport
     */
    public function setDefaultTransport(Transport $transport)
    {
        $this->defaultTransport = $transport;
    }
}
