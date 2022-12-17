<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventSearch\Elastica;

use Elastica\Client;
use Elastica\Document;
use Elastica\Index;
use Elastica\Search;
use Gdbots\Pbj\Marshaler\Elastica\DocumentMarshaler;
use Gdbots\Pbj\Marshaler\Elastica\MappingBuilder;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\ClassUtil;
use Gdbots\Pbj\Util\DateUtil;
use Gdbots\Pbj\Util\NumberUtil;
use Gdbots\Pbjx\Event\EnrichContextEvent;
use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\Exception\EventSearchOperationFailed;
use Gdbots\Pbjx\PbjxEvents;
use Gdbots\QueryParser\ParsedQuery;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Rfc4122\UuidV1;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ElasticaEventSearch implements EventSearch
{
    protected ClientManager $clientManager;
    protected EventDispatcher $dispatcher;
    protected IndexManager $indexManager;
    protected LoggerInterface $logger;
    protected DocumentMarshaler $marshaler;
    protected ?QueryFactory $queryFactory = null;

    /**
     * Used to limit the amount of time a query can take.
     *
     * @var string
     */
    protected string $timeout;

    public function __construct(
        ClientManager $clientManager,
        EventDispatcher $dispatcher,
        IndexManager $indexManager,
        ?LoggerInterface $logger = null,
        ?string $timeout = null
    ) {
        $this->clientManager = $clientManager;
        $this->dispatcher = $dispatcher;
        $this->indexManager = $indexManager;
        $this->logger = $logger ?: new NullLogger();
        $this->timeout = $timeout ?: '100ms';
        $this->marshaler = new DocumentMarshaler();
    }

    final public function createStorage(array $context = []): void
    {
        $context = $this->enrichContext(__FUNCTION__, $context);

        if (isset($context['cluster'])) {
            $clusters = [$context['cluster']];
        } else {
            $clusters = $this->clientManager->getAvailableClusters();
        }

        if (isset($context['index_name'])) {
            $indexName = $context['index_name'];
        } else {
            // by default we update the current index
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $indexName = '*' . $this->indexManager->getIndexPrefix() . '-' . $this->indexManager->getIndexIntervalSuffix($now);
        }

        $templateName = $context['template_name'] ?? $this->indexManager->getIndexPrefix();

        foreach ($clusters as $cluster) {
            $client = $this->clientManager->getClient((string)$cluster);
            $this->indexManager->updateTemplate($client, $templateName);

            if (true === ($context['destroy'] ?? false)) {
                $index = $client->getIndex($indexName);
                try {
                    $index->delete();
                } catch (\Throwable $e) {
                    throw new EventSearchOperationFailed(
                        sprintf(
                            '%s while deleting index [%s].',
                            ClassUtil::getShortName($e),
                            $indexName
                        ),
                        Code::INTERNAL->value,
                        $e
                    );
                }
            } else {
                $this->indexManager->updateIndex($client, $indexName);
            }
        }
    }

    final public function describeStorage(array $context = []): string
    {
        $context = $this->enrichContext(__FUNCTION__, $context);

        if (isset($context['cluster'])) {
            $clusters = [$context['cluster']];
        } else {
            $clusters = $this->clientManager->getAvailableClusters();
        }

        $indexPrefix = $this->indexManager->getIndexPrefix();
        $result = '';

        foreach ($clusters as $cluster) {
            $client = $this->clientManager->getClient((string)$cluster);
            $connection = $client->getConnection();
            $url = "https://{$connection->getHost()}:{$connection->getPort()}";

            // cluster state not allowed on AWS, use cat instead
            $indexes = $client->request('/_cat/indices?h=index')->getData();
            if (is_array($indexes)) {
                $indexes = current($indexes);
            }
            $indexes = explode(PHP_EOL, $indexes);
            $indexes = array_filter(array_map('trim', $indexes));

            foreach ($indexes as $indexName) {
                if (!stristr($indexName, $indexPrefix)) {
                    continue;
                }

                $index = new Index($client, $indexName);
                $result .= <<<TEXT

Service:     ElasticSearch ({$cluster})
Index Name:  {$index->getName()}
Documents:   {$index->count()}
Index Stats: curl "{$url}/{$index->getName()}/_stats?pretty=true"
Mappings:    curl "{$url}/{$index->getName()}/_mapping?pretty=true"

TEXT;
            }
        }

        return $result;
    }

    final public function indexEvents(array $events, array $context = []): void
    {
        if (empty($events)) {
            return;
        }

        $context = $this->enrichContext(__FUNCTION__, $context);
        $client = $this->getClientForWrite($context);
        $this->marshaler->skipValidation(false);
        $documents = [];

        /** @var Message $event */
        foreach ($events as $event) {
            $indexName = null;

            try {
                /** @var \DateTimeInterface $occurredAt */
                $occurredAt = $event->get('occurred_at')->toDateTime();
                $indexName = $this->indexManager->getIndexNameForWrite($event);
                $document = $this->marshaler->marshal($event)
                    ->setId($event->get('event_id')->toString())
                    ->set(
                        IndexManager::OCCURRED_AT_ISO_FIELD_NAME,
                        $occurredAt->format(DateUtil::ISO8601_ZULU)
                    )
                    ->set(MappingBuilder::TYPE_FIELD, $event::schema()->getCurie()->getMessage())
                    ->setIndex($indexName);
                $documents[] = $document;
            } catch (\Throwable $e) {
                $message = sprintf(
                    '%s while adding event [{event_id}] to batch index request ' .
                    'into ElasticSearch [{index_name}].',
                    ClassUtil::getShortName($e)
                );

                $this->logger->error($message, [
                    'exception'  => $e,
                    'event_id'   => $event->get('event_id')->toString(),
                    'pbj'        => $event->toArray(),
                    'index_name' => $indexName,
                ]);
            }
        }

        if (empty($documents)) {
            return;
        }

        try {
            $response = $client->addDocuments($documents);
            if (!$response->isOk()) {
                throw new \Exception($response->getStatus() . '::' . $response->getErrorMessage());
            }
        } catch (\Throwable $e) {
            throw new EventSearchOperationFailed(
                sprintf(
                    '%s while indexing batch into ElasticSearch with message: %s',
                    ClassUtil::getShortName($e),
                    $e->getMessage()
                ),
                Code::INTERNAL->value,
                $e
            );
        }
    }

    final public function deleteEvents(array $eventIds, array $context = []): void
    {
        if (empty($eventIds)) {
            return;
        }

        $context = $this->enrichContext(__FUNCTION__, $context);
        $client = $this->getClientForWrite($context);
        $documents = [];

        foreach ($eventIds as $eventId) {
            $indexName = null;

            try {
                // this will be correct *most* of the time.
                /** @var UuidV1 $timeUuid */
                $timeUuid = UuidV1::fromString($eventId->toString());
                $indexName = $this->indexManager->getIndexNameFromContext($timeUuid->getDateTime(), $context);
                $documents[] = (new Document())
                    ->setId((string)$eventId)
                    ->setIndex($indexName);
            } catch (\Throwable $e) {
                $message = sprintf(
                    '%s while adding event [{event_id}] to batch delete request ' .
                    'from ElasticSearch [{index_name}].',
                    ClassUtil::getShortName($e)
                );

                $this->logger->error($message, [
                    'exception'  => $e,
                    'index_name' => $indexName,
                    'event_id'   => (string)$eventId,
                ]);
            }
        }

        if (empty($documents)) {
            return;
        }

        try {
            $response = $client->deleteDocuments($documents);
            if ($response->hasError()) {
                throw new \Exception($response->getStatus() . '::' . $response->getErrorMessage());
            }
        } catch (\Throwable $e) {
            throw new EventSearchOperationFailed(
                sprintf(
                    '%s while deleting batch from ElasticSearch with message: %s',
                    ClassUtil::getShortName($e),
                    $e->getMessage()
                ),
                Code::INTERNAL->value,
                $e
            );
        }
    }

    final public function searchEvents(Message $request, ParsedQuery $parsedQuery, Message $response, array $curies = [], array $context = []): void
    {
        $context = $this->enrichContext(__FUNCTION__, $context);
        $skipValidation = filter_var($context['skip_validation'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $client = $this->getClientForRead($context);
        $search = new Search($client);

        $indices = array_map(fn(string $index) => new Index($client, $index), $this->indexManager->getIndexNamesForSearch($request));
        $search->addIndices($indices);

        $page = $request->get('page');
        $perPage = $request->get('count');
        $offset = ($page - 1) * $perPage;
        $offset = NumberUtil::bound($offset, 0, 10000);
        $options = [
            Search::OPTION_TIMEOUT                   => $this->timeout,
            Search::OPTION_FROM                      => $offset,
            Search::OPTION_SIZE                      => $perPage,
            Search::OPTION_SEARCH_IGNORE_UNAVAILABLE => true,
        ];

        try {
            $results = $search
                ->setOptionsAndQuery($options, $this->getQueryFactory()->create($request, $parsedQuery, $curies))
                ->search();
        } catch (\Throwable $e) {
            $this->logger->error(
                'ElasticSearch query [{query}] failed.',
                [
                    'exception'  => $e,
                    'pbj_schema' => $request->schema()->getId()->toString(),
                    'pbj'        => $request->toArray(),
                    'query'      => $request->get('q'),
                ]
            );

            throw new EventSearchOperationFailed(
                sprintf(
                    'ElasticSearch query [%s] failed with message: %s',
                    $request->get('q'),
                    ClassUtil::getShortName($e) . '::' . $e->getMessage()
                ),
                Code::INTERNAL->value,
                $e
            );
        }

        $events = [];
        $this->marshaler->skipValidation($skipValidation);
        foreach ($results->getResults() as $result) {
            try {
                $events[] = $this->marshaler->unmarshal($result->getSource());
            } catch (\Throwable $e) {
                $this->logger->error(
                    'Source returned from ElasticSearch could not be unmarshaled.',
                    ['exception' => $e, 'hit' => $result->getHit()]
                );
            }
        }
        $this->marshaler->skipValidation(false);

        $response
            ->set('total', $results->getTotalHits())
            ->set('has_more', ($offset + $perPage) < $results->getTotalHits() && $offset < 10000)
            ->set('time_taken', (int)($results->getResponse()->getQueryTime() * 1000))
            ->set('max_score', $results->getMaxScore())
            ->addToList('events', $events);
    }

    /**
     * Override to provide your own logic which determines which client
     * to use for a READ operation based on the context provided.
     * Typically used for multi-tenant applications.
     *
     * @param array $context
     *
     * @return Client
     */
    protected function getClientForRead(array $context): Client
    {
        return $this->getClientForWrite($context);
    }

    /**
     * Override to provide your own logic which determines which client
     * to use for a WRITE operation based on the context provided.
     * Typically used for multi-tenant applications.
     *
     * @param array $context
     *
     * @return Client
     */
    protected function getClientForWrite(array $context): Client
    {
        return $this->clientManager->getClient($context['cluster'] ?? 'default');
    }

    protected function enrichContext(string $operation, array $context): array
    {
        if (isset($context['already_enriched'])) {
            return $context;
        }

        $event = new EnrichContextEvent('event_search', $operation, $context);
        $context = $this->dispatcher->dispatch($event, PbjxEvents::ENRICH_CONTEXT)->all();
        $context['already_enriched'] = true;
        return $context;
    }

    final protected function getQueryFactory(): QueryFactory
    {
        if (null === $this->queryFactory) {
            $this->queryFactory = $this->doGetQueryFactory();
        }

        return $this->queryFactory;
    }

    protected function doGetQueryFactory(): QueryFactory
    {
        return new QueryFactory();
    }
}
