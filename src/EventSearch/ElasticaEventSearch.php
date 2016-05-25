<?php

namespace Gdbots\Pbjx\EventSearch;

use Elastica\Document;
use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\DateUtils;
use Gdbots\Pbj\Marshaler\Elastica\DocumentMarshaler;
use Gdbots\Pbjx\Exception\EventSearchOperationFailed;
use Gdbots\QueryParser\ParsedQuery;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\SearchEventsRequest\SearchEventsRequest;
use Gdbots\Schemas\Pbjx\Mixin\SearchEventsResponse\SearchEventsResponse;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ElasticaEventSearch implements EventSearch
{
    /** @var ElasticaClientManager */
    protected $clientManager;

    /** @var ElasticaIndexManager */
    protected $indexManager;

    /** @var LoggerInterface */
    protected $logger;

    /** @var DocumentMarshaler */
    protected $marshaler;

    /**
     * @param ElasticaClientManager $clientManager
     * @param ElasticaIndexManager $indexManager
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        ElasticaClientManager $clientManager,
        ElasticaIndexManager $indexManager,
        LoggerInterface $logger = null
    ) {
        $this->clientManager = $clientManager;
        $this->indexManager = $indexManager;
        $this->logger = $logger ?: new NullLogger();
        $this->marshaler = new DocumentMarshaler();
    }

    /**
     * {@inheritdoc}
     * @param Event[] $events
     */
    final public function index(array $events)
    {
        if (empty($events)) {
            return;
        }

        $client = $this->getClientForWrite($events[0]);
        $documents = [];

        foreach ($events as $event) {
            $indexName = null;
            $typeName = null;

            try {
                $schema = $event::schema();
                /** @var \DateTime $occurredAt */
                $occurredAt = $event->get('occurred_at')->toDateTime();
                $indexName = $this->indexManager->getIndexNameForWrite($event);
                $typeName = $schema->getCurie()->getMessage();

                $document = $this->marshaler->marshal($event)
                    ->setId($event->get('event_id')->toString())
                    ->set(
                        ElasticaIndexManager::OCCURRED_AT_ISO_FIELD_NAME,
                        $occurredAt->format(DateUtils::ISO8601_ZULU)
                    )
                    ->setType($typeName)
                    ->setIndex($indexName);

                $this->beforeIndex($document, $event);
                $documents[] = $document;

            } catch (\Exception $e) {
                $message = sprintf(
                    '%s::Failed to add event [{event_id}] to batch index request ' .
                    'into ElasticSearch [{index_name}/{type_name}].',
                    ClassUtils::getShortName($event->getException())
                );

                $this->logger->error($message, [
                    'exception' => $event->getException(),
                    'event_id' => $event->get('event_id')->toString(),
                    'pbj' => $event->getMessage()->toArray(),
                    'index_name' => $indexName,
                    'type_name' => $typeName,
                ]);
            }
        }

        if (empty($documents)) {
            return;
        }

        try {
            $response = $client->addDocuments($documents);
            if (!$response->isOk()) {
                throw new \Exception($response->getStatus() . '::' . $response->getError());
            }
        } catch (\Exception $e) {
            throw new EventSearchOperationFailed(
                sprintf(
                    'Failed to index batch into ElasticSearch with message: %s',
                    ClassUtils::getShortName($e) . '::' . $e->getMessage()
                ),
                Code::INTERNAL,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function search(SearchEventsRequest $request, ParsedQuery $parsedQuery, SearchEventsResponse $response)
    {
        // TODO: Implement search() method.
        $client = $this->getClientForSearch($request);
        return $response;
    }

    /**
     * Returns an elastica client for a WRITE operation based on the incoming message.
     * The message is provided so you can dynamically decide which cluster to use
     * based on the message content itself.  (e.g. for multi-tenant apps)
     *
     * @param Event $event
     * @return \Elastica\Client
     */
    protected function getClientForWrite(Event $event)
    {
        // override to provide your own logic for client creation.
        return $this->clientManager->getClient();
    }

    /**
     * Returns an elastica client for a SEARCH operation based on the incoming message.
     * The message is provided so you can dynamically decide which cluster to use
     * based on the message content itself.  (e.g. for multi-tenant apps)
     *
     * @param SearchEventsRequest $request
     * @return \Elastica\Client
     */
    protected function getClientForSearch(SearchEventsRequest $request)
    {
        // override to provide your own logic for client creation.
        return $this->clientManager->getClient();
    }

    /**
     * @param Document $document
     * @param Event $event
     */
    protected function beforeIndex(Document $document, Event $event)
    {
        // Override to customize the document before it is indexed.
    }
}
