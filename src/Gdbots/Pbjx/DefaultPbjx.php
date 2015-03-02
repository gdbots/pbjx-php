<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Extension\Command;
use Gdbots\Pbj\Extension\DomainEvent;
use Gdbots\Pbj\Extension\Request;
use Gdbots\Pbjx\Event\EnrichCommandEvent;
use Gdbots\Pbjx\Event\EnrichDomainEventEvent;
use Gdbots\Pbjx\Event\ValidateCommandEvent;

class DefaultPbjx implements Pbjx
{
    /** @var Dispatcher */
    protected $dispatcher;

    /** @var ServiceLocator */
    protected $locator;

    /**
     * @param ServiceLocator $locator
     */
    public function __construct(ServiceLocator $locator)
    {
        $this->locator = $locator;
        $this->dispatcher = $this->locator->getDispatcher();
    }

    /**
     * {@inheritdoc}
     */
    public function send(Command $command)
    {
        $curie = $command::schema()->getId()->getCurie()->toString();

        $event = new ValidateCommandEvent($command);
        $this->dispatcher->dispatch(PbjxEvents::COMMAND_VALIDATE, $event);
        $this->dispatcher->dispatch($curie . '.validate', $event);

        $event = new EnrichCommandEvent($command);
        $this->dispatcher->dispatch(PbjxEvents::COMMAND_ENRICH, $event);
        $this->dispatcher->dispatch($curie . '.enrich', $event);

        $this->locator->getCommandBus()->send($command->freeze());
    }

    /**
     * {@inheritdoc}
     */
    public function publish(DomainEvent $domainEvent)
    {
        $curie = $domainEvent::schema()->getId()->getCurie()->toString();

        $event = new EnrichDomainEventEvent($domainEvent);
        $this->dispatcher->dispatch(PbjxEvents::EVENT_ENRICH, $event);
        $this->dispatcher->dispatch($curie . '.enrich', $event);

        $this->locator->getEventBus()->publish($domainEvent->freeze());
    }

    /**
     * {@inheritdoc}
     */
    public function request(Request $request)
    {
        return $this->locator->getRequestBus()->request($request->freeze());
    }
}
