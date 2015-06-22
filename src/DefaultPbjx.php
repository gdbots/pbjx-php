<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\DomainCommand;
use Gdbots\Pbj\DomainEvent;
use Gdbots\Pbj\DomainRequest;
use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Event\GetResponseEvent;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\Event\PostResponseEvent;
use Gdbots\Pbjx\Exception\InvalidArgumentException;
use Gdbots\Pbjx\Exception\RequestHandlingFailed;
use Gdbots\Pbjx\Request\RequestFailedResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DefaultPbjx implements Pbjx
{
    /** @var EventDispatcherInterface */
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
        PbjxEvent::setPbjx($this);
    }

    /**
     * {@inheritdoc}
     */
    public function trigger(Message $message, $suffix, PbjxEvent $event = null)
    {
        $suffix = '.' . trim($suffix, '.');
        if ('.' === $suffix) {
            throw new InvalidArgumentException('Trigger requires a non-empty suffix.');
        }
        $event = $event ?: new PbjxEvent($message);

        $schema = $message::schema();
        $this->dispatcher->dispatch($schema->getCurieWithMajorRev() . $suffix, $event);
        $this->dispatcher->dispatch($schema->getCurie()->toString() . $suffix, $event);
        foreach ($schema->getMixinIds() as $mixinId) {
            $this->dispatcher->dispatch($mixinId . $suffix, $event);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function send(DomainCommand $command)
    {
        $event = new PbjxEvent($command);
        $this->trigger($command, PbjxEvents::SUFFIX_VALIDATE, $event);
        $this->trigger($command, PbjxEvents::SUFFIX_ENRICH, $event);
        $this->locator->getCommandBus()->send($command);
    }

    /**
     * {@inheritdoc}
     */
    public function publish(DomainEvent $domainEvent)
    {
        $this->locator->getEventBus()->publish($domainEvent);
    }

    /**
     * {@inheritdoc}
     */
    public function request(DomainRequest $request)
    {
        $event = new PbjxEvent($request);
        $this->trigger($request, PbjxEvents::SUFFIX_VALIDATE, $event);
        $this->trigger($request, PbjxEvents::SUFFIX_ENRICH, $event);
        $event = new GetResponseEvent($request);
        $this->trigger($request, PbjxEvents::SUFFIX_BEFORE_HANDLE, $event);

        if ($event->hasResponse()) {
            return $event->getResponse();
        }

        $response = $this->locator->getRequestBus()->request($request);
        $event->setResponse($response);

        if ($response instanceof RequestFailedResponse) {
            throw new RequestHandlingFailed($response);
        }

        try {
            $event = new PostResponseEvent($request, $response);
            $this->trigger($request, PbjxEvents::SUFFIX_AFTER_HANDLE, $event);
            $this->trigger($response, PbjxEvents::SUFFIX_CREATED, $event);
        } catch (\Exception $e) {
            $this->locator->getExceptionHandler()->onRequestBusException(
                new BusExceptionEvent($response, $e)
            );
        }

        return $response;
    }
}
