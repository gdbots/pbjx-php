<?php
declare(strict_types=1);

namespace Gdbots\Pbjx;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\Schema;
use Gdbots\Pbj\Util\NumberUtil;
use Gdbots\Pbjx\Event\BusExceptionEvent;
use Gdbots\Pbjx\Event\GetResponseEvent;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Pbjx\Event\ResponseCreatedEvent;
use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\EventStore\EventStore;
use Gdbots\Pbjx\Exception\InvalidArgumentException;
use Gdbots\Pbjx\Exception\LogicException;
use Gdbots\Pbjx\Exception\RequestHandlingFailed;
use Gdbots\Pbjx\Exception\TooMuchRecursion;
use Gdbots\Schemas\Pbjx\Request\RequestFailedResponseV1;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class SimplePbjx implements Pbjx
{
    private EventDispatcherInterface $dispatcher;
    private ServiceLocator $locator;
    private int $maxRecursion;

    public function __construct(ServiceLocator $locator, int $maxRecursion = 10)
    {
        $this->locator = $locator;
        $this->dispatcher = $this->locator->getDispatcher();
        $this->maxRecursion = NumberUtil::bound($maxRecursion, 2, 10);
        PbjxEvent::setPbjx($this);
    }

    public function trigger(Message $message, string $suffix, ?PbjxEvent $event = null, bool $recursive = true): self
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

        $this->dispatcher->dispatch($event, 'gdbots_pbjx.message' . $suffix);

        foreach ($schema->getMixins() as $mixin) {
            $this->dispatcher->dispatch($event, $mixin . $suffix);
        }

        $this->dispatcher->dispatch($event, $schema->getCurieMajor() . $suffix);
        $this->dispatcher->dispatch($event, $schema->getCurie()->toString() . $suffix);

        return $this;
    }

    public function triggerLifecycle(Message $message, bool $recursive = true): self
    {
        if ($message->isFrozen()) {
            return $this;
        }

        $event = new PbjxEvent($message);
        $this->trigger($message, PbjxEvents::SUFFIX_BIND, $event, $recursive);
        $this->trigger($message, PbjxEvents::SUFFIX_VALIDATE, $event, $recursive);
        $this->trigger($message, PbjxEvents::SUFFIX_ENRICH, $event, $recursive);
        return $this;
    }

    public function copyContext(Message $from, Message $to): self
    {
        if ($to->isFrozen()) {
            return $this;
        }

        $schema = $to::schema();

        if (!$to->has('ctx_causator_ref') && $schema->hasField('ctx_causator_ref')) {
            $to->set('ctx_causator_ref', $from->generateMessageRef());
        }

        $clone = ['ctx_app', 'ctx_cloud'];

        foreach ($clone as $field) {
            if (!$to->has($field) && $from->has($field) && $schema->hasField($field)) {
                $to->set($field, clone $from->get($field));
            }
        }

        $simple = [
            'ctx_tenant_id',
            'ctx_correlator_ref',
            'ctx_user_ref',
            'ctx_ip',
            'ctx_ipv6',
            'ctx_ua',
            'ctx_msg',
        ];

        foreach ($simple as $field) {
            if (!$to->has($field) && $from->has($field) && $schema->hasField($field)) {
                $to->set($field, $from->get($field));
            }
        }

        return $this;
    }

    public function send(Message $command): void
    {
        if (!$command::schema()->hasMixin('gdbots:pbjx:mixin:command')) {
            throw new LogicException('Pbjx->send requires a message using "gdbots:pbjx:mixin:command".');
        }

        $this->triggerLifecycle($command);
        $this->locator->getCommandBus()->send($command);
    }

    public function sendAt(Message $command, int $timestamp, ?string $jobId = null, array $context = []): string
    {
        if ($timestamp <= time()) {
            throw new LogicException('Pbjx->sendAt requires a timestamp in the future.');
        }

        if (!$command::schema()->hasMixin('gdbots:pbjx:mixin:command')) {
            throw new LogicException('Pbjx->sendAt requires a message using "gdbots:pbjx:mixin:command".');
        }

        $this->triggerLifecycle($command);
        $command->freeze();
        return $this->locator->getScheduler()->sendAt($command, $timestamp, $jobId, $context);
    }

    public function cancelJobs(array $jobIds, array $context = []): void
    {
        $this->locator->getScheduler()->cancelJobs($jobIds, $context);
    }

    public function publish(Message $event): void
    {
        if (!$event::schema()->hasMixin('gdbots:pbjx:mixin:event')) {
            throw new LogicException('Pbjx->publish requires a message using "gdbots:pbjx:mixin:event".');
        }

        $this->triggerLifecycle($event);
        $this->locator->getEventBus()->publish($event);
    }

    public function request(Message $request): Message
    {
        if (!$request::schema()->hasMixin('gdbots:pbjx:mixin:request')) {
            throw new LogicException('Pbjx->request requires a message using "gdbots:pbjx:mixin:request".');
        }

        $this->triggerLifecycle($request);
        $event = new GetResponseEvent($request);
        $this->trigger($request, PbjxEvents::SUFFIX_BEFORE_HANDLE, $event, false);

        if ($event->hasResponse()) {
            return $event->getResponse();
        }

        $response = $this->locator->getRequestBus()->request($request);
        $event->setResponse($response);

        if ($response instanceof RequestFailedResponseV1) {
            throw new RequestHandlingFailed($response);
        }

        try {
            $event = new ResponseCreatedEvent($request, $response);
            $this->trigger($request, PbjxEvents::SUFFIX_AFTER_HANDLE, $event, false);
            $this->trigger($response, PbjxEvents::SUFFIX_CREATED, $event, false);
        } catch (\Throwable $e) {
            $this->locator->getExceptionHandler()->onRequestBusException(new BusExceptionEvent($response, $e));
            throw $e;
        }

        return $response;
    }

    public function getEventStore(): EventStore
    {
        return $this->locator->getEventStore();
    }

    public function getEventSearch(): EventSearch
    {
        return $this->locator->getEventSearch();
    }

    /**
     * @param Message $message
     * @param Schema  $schema
     *
     * @return Message[]
     */
    private function getNestedMessages(Message $message, Schema $schema): array
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
                $messages[] = $message->fget($field->getName());
            } else {
                $messages = array_merge($messages, $message->fget($field->getName()));
            }
        }

        return $messages;
    }
}
