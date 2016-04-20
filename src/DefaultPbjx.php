<?php

namespace Gdbots\Pbjx;

use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\Schema;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Event\GetResponseEvent;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\Event\PostResponseEvent;
use Gdbots\Pbjx\Exception\InvalidArgumentException;
use Gdbots\Pbjx\Exception\RequestHandlingFailed;
use Gdbots\Pbjx\Exception\TooMuchRecursion;
use Gdbots\Schemas\Pbjx\Mixin\Command\Command;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Request\Request;
use Gdbots\Schemas\Pbjx\Request\RequestFailedResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DefaultPbjx implements Pbjx
{
    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /** @var ServiceLocator */
    protected $locator;

    /** @var int */
    protected $maxRecursion = 10;

    /**
     * @param ServiceLocator $locator
     * @param int $maxRecursion
     */
    public function __construct(ServiceLocator $locator, $maxRecursion = 10)
    {
        $this->locator = $locator;
        $this->dispatcher = $this->locator->getDispatcher();
        $this->maxRecursion = NumberUtils::bound($maxRecursion, 2, 10);
        PbjxEvent::setPbjx($this);
    }

    /**
     * {@inheritdoc}
     */
    public function trigger(Message $message, $suffix, PbjxEvent $event = null, $recursive = true)
    {
        $suffix = '.' . trim($suffix, '.');
        if ('.' === $suffix) {
            throw new InvalidArgumentException('Trigger requires a non-empty suffix.');
        }

        $event = $event ?: new PbjxEvent($message);
        $schema = $message::schema();

        if ($event->getDepth() > $this->maxRecursion) {
            throw new TooMuchRecursion(sprintf(
                'Pbjx::trigger encountered a schema that is too complex ' .
                'or a nested message is being referenced multiple times in ' .
                'the same tree.  Max recursion: %d, Current schema is "%s".',
                $this->maxRecursion,
                $schema->getId()->toString()
            ));
        }

        if ($recursive && $event->supportsRecursion()) {
            foreach ($this->getNestedMessages($message, $schema) as $nestedMessage) {
                if ($nestedMessage->isFrozen()) {
                    continue;
                }

                $this->trigger($nestedMessage, $suffix, $event->createChildEvent($nestedMessage), $recursive);
            }
        }

        $this->dispatcher->dispatch('gdbots_pbjx.message' . $suffix, $event);

        foreach ($schema->getMixinIds() as $mixinId) {
            $this->dispatcher->dispatch($mixinId . $suffix, $event);
        }

        foreach ($schema->getMixinCuries() as $mixinCurie) {
            $this->dispatcher->dispatch($mixinCurie . $suffix, $event);
        }

        $this->dispatcher->dispatch($schema->getCurieMajor() . $suffix, $event);
        $this->dispatcher->dispatch($schema->getCurie()->toString() . $suffix, $event);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function triggerLifecycle(Message $message, $recursive = true)
    {
        $event = new PbjxEvent($message);
        $this->trigger($message, PbjxEvents::SUFFIX_BIND, $event, $recursive);
        $this->trigger($message, PbjxEvents::SUFFIX_VALIDATE, $event, $recursive);
        $this->trigger($message, PbjxEvents::SUFFIX_ENRICH, $event, $recursive);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function copyContext(Message $from, Message $to)
    {
        if (!$to->has('ctx_causator_ref')) {
            $to->set('ctx_causator_ref', $from->generateMessageRef());
        }

        if (!$to->has('ctx_app') && $from->has('ctx_app')) {
            $to->set('ctx_app', clone $from->get('ctx_app'));
        }

        if (!$to->has('ctx_cloud') && $from->has('ctx_cloud')) {
            $to->set('ctx_cloud', clone $from->get('ctx_cloud'));
        }

        foreach (['stream_id', 'ctx_correlator_ref', 'ctx_user_ref', 'ctx_ip', 'ctx_ua'] as $ctx) {
            if (!$to->has($ctx) && $from->has($ctx)) {
                $to->set($ctx, $from->get($ctx));
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Command $command)
    {
        if (!$command->isFrozen()) {
            $this->triggerLifecycle($command);
        }

        $this->locator->getCommandBus()->send($command);
    }

    /**
     * {@inheritdoc}
     */
    public function publish(Event $event)
    {
        if (!$event->isFrozen()) {
            $this->triggerLifecycle($event);
        }

        $this->locator->getEventBus()->publish($event);
    }

    /**
     * {@inheritdoc}
     */
    public function request(Request $request)
    {
        $this->triggerLifecycle($request);
        $event = new GetResponseEvent($request);
        $this->trigger($request, PbjxEvents::SUFFIX_BEFORE_HANDLE, $event, false);

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
            $this->trigger($request, PbjxEvents::SUFFIX_AFTER_HANDLE, $event, false);
            $this->trigger($response, PbjxEvents::SUFFIX_CREATED, $event, false);
        } catch (\Exception $e) {
            $this->locator->getExceptionHandler()->onRequestBusException(new BusExceptionEvent($response, $e));
        }

        return $response;
    }

    /**
     * @param Message $message
     * @param Schema $schema
     *
     * @return Message[]
     */
    protected function getNestedMessages(Message $message, Schema $schema)
    {
        $messages = [];

        foreach ($schema->getFields() as $field) {
            if (!$field->getType()->isMessage()) {
                continue;
            }

            if (!$message->has($field->getName())) {
                continue;
            }

            if ($field->isASingleValue()) {
                $messages[] = $message->get($field->getName());
            } else {
                $messages = array_merge($messages, array_values($message->get($field->getName())));
            }
        }

        return $messages;
    }
}
