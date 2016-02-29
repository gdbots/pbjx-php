<?php

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Event\GetResponseEvent;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\Event\PostResponseEvent;
use Gdbots\Pbjx\Exception\InvalidArgumentException;
use Gdbots\Pbjx\Exception\RequestHandlingFailed;
use Gdbots\Schemas\Pbj\Command\Command;
use Gdbots\Schemas\Pbj\Event\Event;
use Gdbots\Schemas\Pbj\Request\Request;
use Gdbots\Schemas\Pbjx\Request\RequestFailedResponse;
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

        foreach ($schema->getMixinIds() as $mixinId) {
            $this->dispatcher->dispatch($mixinId . $suffix, $event);
        }

        foreach ($schema->getMixinCuries() as $mixinCurie) {
            $this->dispatcher->dispatch($mixinCurie . $suffix, $event);
        }

        $this->dispatcher->dispatch($schema->getCurieWithMajorRev() . $suffix, $event);
        $this->dispatcher->dispatch($schema->getCurie()->toString() . $suffix, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Command $command)
    {
        if (!$command->isFrozen()) {
            $event = new PbjxEvent($command);
            $this->trigger($command, PbjxEvents::SUFFIX_BIND, $event);
            $this->trigger($command, PbjxEvents::SUFFIX_VALIDATE, $event);
            $this->trigger($command, PbjxEvents::SUFFIX_ENRICH, $event);
        }

        $this->locator->getCommandBus()->send($command);
    }

    /**
     * {@inheritdoc}
     */
    public function publish(Event $event)
    {
        if (!$event->isFrozen()) {
            $pbjxEvent = new PbjxEvent($event);
            $this->trigger($event, PbjxEvents::SUFFIX_BIND, $pbjxEvent);
            $this->trigger($event, PbjxEvents::SUFFIX_VALIDATE, $pbjxEvent);
            $this->trigger($event, PbjxEvents::SUFFIX_ENRICH, $pbjxEvent);
        }

        $this->locator->getEventBus()->publish($event);
    }

    /**
     * {@inheritdoc}
     */
    public function request(Request $request)
    {
        $event = new PbjxEvent($request);
        $this->trigger($request, PbjxEvents::SUFFIX_BIND, $event);
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
            $this->locator->getExceptionHandler()->onRequestBusException(new BusExceptionEvent($response, $e));
        }

        return $response;
    }
}
