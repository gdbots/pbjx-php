<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\MessageCurie;
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
    public function getCommandHandler(MessageCurie $curie)
    {
        if (isset($this->handlers[$curie->toString()])) {
            return $this->handlers[$curie->toString()];
        }
        throw new HandlerNotFound($curie);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestHandler(MessageCurie $curie)
    {
        if (isset($this->handlers[$curie->toString()])) {
            return $this->handlers[$curie->toString()];
        }
        throw new HandlerNotFound($curie);
    }

    /**
     * @param MessageCurie $curie
     * @param CommandHandler $handler
     */
    public function registerCommandHandler(MessageCurie $curie, CommandHandler $handler)
    {
        $this->handlers[$curie->toString()] = $handler;
    }

    /**
     * @param MessageCurie $curie
     * @param RequestHandler $handler
     */
    public function registerRequestHandler(MessageCurie $curie, RequestHandler $handler)
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
