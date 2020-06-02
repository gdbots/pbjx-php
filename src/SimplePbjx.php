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
use Gdbots\Schemas\Pbjx\Mixin\Command\CommandV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Request\RequestV1Mixin;
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

        if (!$to->has(CommandV1Mixin::CTX_CAUSATOR_REF_FIELD)
            && $schema->hasField(CommandV1Mixin::CTX_CAUSATOR_REF_FIELD)
        ) {
            $to->set(CommandV1Mixin::CTX_CAUSATOR_REF_FIELD, $from->generateMessageRef());
        }

        $clone = [
            CommandV1Mixin::CTX_APP_FIELD,
            CommandV1Mixin::CTX_CLOUD_FIELD,
        ];

        foreach ($clone as $field) {
            if (!$to->has($field) && $from->has($field) && $schema->hasField($field)) {
                $to->set($field, clone $from->get($field));
            }
        }

        $simple = [
            CommandV1Mixin::CTX_TENANT_ID_FIELD,
            CommandV1Mixin::CTX_CORRELATOR_REF_FIELD,
            CommandV1Mixin::CTX_USER_REF_FIELD,
            CommandV1Mixin::CTX_IP_FIELD,
            CommandV1Mixin::CTX_IPV6_FIELD,
            CommandV1Mixin::CTX_UA_FIELD,
            CommandV1Mixin::CTX_MSG_FIELD,
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
        if (!$command::schema()->hasMixin(CommandV1Mixin::SCHEMA_CURIE)) {
            throw new LogicException('Pbjx->send requires a message using "' . CommandV1Mixin::SCHEMA_CURIE . '".');
        }

        $this->triggerLifecycle($command);
        $this->locator->getCommandBus()->send($command);
    }

    public function sendAt(Message $command, int $timestamp, ?string $jobId = null): string
    {
        if ($timestamp <= time()) {
            throw new LogicException('Pbjx->sendAt requires a timestamp in the future.');
        }

        if (!$command::schema()->hasMixin(CommandV1Mixin::SCHEMA_CURIE)) {
            throw new LogicException('Pbjx->sendAt requires a message using "' . CommandV1Mixin::SCHEMA_CURIE . '".');
        }

        $this->triggerLifecycle($command);
        $command->freeze();
        return $this->locator->getScheduler()->sendAt($command, $timestamp, $jobId);
    }

    public function cancelJobs(array $jobIds): void
    {
        $this->locator->getScheduler()->cancelJobs($jobIds);
    }

    public function publish(Message $event): void
    {
        if (!$event::schema()->hasMixin(EventV1Mixin::SCHEMA_CURIE)) {
            throw new LogicException('Pbjx->publish requires a message using "' . EventV1Mixin::SCHEMA_CURIE . '".');
        }

        $this->triggerLifecycle($event);
        $this->locator->getEventBus()->publish($event);
    }

    public function request(Message $request): Message
    {
        if (!$request::schema()->hasMixin(RequestV1Mixin::SCHEMA_CURIE)) {
            throw new LogicException('Pbjx->request requires a message using "' . RequestV1Mixin::SCHEMA_CURIE . '".');
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
