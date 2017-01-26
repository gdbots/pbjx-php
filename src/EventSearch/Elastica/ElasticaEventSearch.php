<?php
declare(strict_types = 1);

namespace Gdbots\Pbjx\EventSearch\Elastica;

use Elastica\Client;
use Elastica\Index;
use Elastica\Search;
use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\DateUtils;
use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\Marshaler\Elastica\DocumentMarshaler;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\EventSearch\EventSearch;
use Gdbots\Pbjx\Exception\EventSearchOperationFailed;
use Gdbots\QueryParser\ParsedQuery;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Indexed\Indexed;
use Gdbots\Schemas\Pbjx\Mixin\SearchEventsRequest\SearchEventsRequest;
use Gdbots\Schemas\Pbjx\Mixin\SearchEventsResponse\SearchEventsResponse;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ElasticaEventSearch implements EventSearch
{
    /** @var ClientManager */
    protected $clientManager;

    /** @var LoggerInterface */
    protected $logger;

    /** @var IndexManager */
    private $indexManager;

    /** @var DocumentMarshaler */
    private $marshaler;

    /** @var QueryFactory */
    private $queryFactory;

    /**
     * Used to limit the amount of time a query can take.
     *
     * @var string
     */
    private $timeout;

    /**
     * @param ClientManager   $clientManager
     * @param IndexManager    $indexManager
     * @param LoggerInterface $logger
     * @param string          $timeout
     */
    public function __construct(
        ClientManager $clientManager,
        IndexManager $indexManager,
        ?LoggerInterface $logger = null,
        ?string $timeout = null
    ) {
        $this->clientManager = $clientManager;
        $this->indexManager = $indexManager;
        $this->logger = $logger ?: new NullLogger();
        $this->timeout = $timeout ?: '100ms';
        $this->marshaler = new DocumentMarshaler();
    }

    /**
     * {@inheritdoc}
     */
    final public function createStorage(array $context = []): void
    {
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
            $client = $this->clientManager->getClient($cluster);
            $this->indexManager->updateTemplate($client, $templateName);
            $this->indexManager->updateIndex($client, $indexName);
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function describeStorage(array $context = []): string
    {
        if (isset($context['cluster'])) {
            $clusters = [$context['cluster']];
        } else {
            $clusters = $this->clientManager->getAvailableClusters();
        }

        $indexPrefix = $this->indexManager->getIndexPrefix();
        $result = '';

        foreach ($clusters as $cluster) {
            $client = $this->clientManager->getClient($cluster);
            $connection = $client->getConnection();
            $url = "http://{$connection->getHost()}:{$connection->getPort()}";

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

Service:       ElasticSearch ({$cluster})
Index Name:    {$index->getName()}
Documents:     {$index->count()}
Index Stats:   curl "{$url}/{$index->getName()}/_stats?pretty=1"
Type Mappings: curl "{$url}/{$index->getName()}/_mapping?pretty=1"

TEXT;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    final public function indexEvents(array $events, array $context = []): void
    {
        if (empty($events)) {
            return;
        }

        $client = $this->getClientForWrite($context);
        $documents = [];

        /** @var Indexed $event */
        foreach ($events as $event) {
            $schema = $event::schema();
            $indexName = null;
            $typeName = null;

            try {
                /** @var \DateTime $occurredAt */
                $occurredAt = $event->get('occurred_at')->toDateTime();
                $indexName = $this->indexManager->getIndexNameForWrite($event);
                $typeName = $schema->getCurie()->getMessage();
                $document = $this->marshaler->marshal($event)
                    ->setId($event->get('event_id')->toString())
                    ->set(
                        IndexManager::OCCURRED_AT_ISO_FIELD_NAME,
                        $occurredAt->format(DateUtils::ISO8601_ZULU)
                    )
                    ->setType($typeName)
                    ->setIndex($indexName);
                $documents[] = $document;
            } catch (\Exception $e) {
                $message = sprintf(
                    '%s while adding event [{event_id}] to batch index request ' .
                    'into ElasticSearch [{index_name}/{type_name}].',
                    ClassUtils::getShortName($e)
                );

                $this->logger->error($message, [
                    'exception'  => $e,
                    'event_id'   => $event->get('event_id')->toString(),
                    'pbj'        => $event->toArray(),
                    'index_name' => $indexName,
                    'type_name'  => $typeName,
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
                    '%s while indexing batch into ElasticSearch with message: %s',
                    ClassUtils::getShortName($e),
                    $e->getMessage()
                ),
                Code::INTERNAL,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function searchEvents(SearchEventsRequest $request, ParsedQuery $parsedQuery, SearchEventsResponse $response, array $curies = [], array $context = []): void
    {
        $search = new Search($this->getClientForRead($context));
        $search->addIndices($this->indexManager->getIndexNamesForSearch($request));
        /** @var SchemaCurie $curie */
        foreach ($curies as $curie) {
            $search->addType($curie->getMessage());
        }

        $page = $request->get('page');
        $perPage = $request->get('count');
        $offset = ($page - 1) * $perPage;
        $offset = NumberUtils::bound($offset, 0, 1000);
        $options = [
            Search::OPTION_TIMEOUT                   => $this->timeout,
            Search::OPTION_FROM                      => $offset,
            Search::OPTION_SIZE                      => $perPage,
            Search::OPTION_SEARCH_IGNORE_UNAVAILABLE => true,
        ];

        try {
            $results = $search
                ->setOptionsAndQuery($options, $this->getQueryFactory()->create($request, $parsedQuery))
                ->search();
        } catch (\Exception $e) {
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
                    ClassUtils::getShortName($e) . '::' . $e->getMessage()
                ),
                Code::INTERNAL,
                $e
            );
        }

        $events = [];
        foreach ($results->getResults() as $result) {
            try {
                $events[] = $this->marshaler->unmarshal($result->getSource());
            } catch (\Exception $e) {
                $this->logger->error(
                    'Source returned from ElasticSearch could not be unmarshaled.',
                    ['exception' => $e, 'hit' => $result->getHit()]
                );
            }
        }

        $response
            ->set('total', $results->getTotalHits())
            ->set('has_more', ($offset + $perPage) < $results->getTotalHits() && $offset < 1000)
            ->set('time_taken', (int)$results->getTotalTime())
            ->set('max_score', (float)$results->getMaxScore())
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

    /**
     * @return QueryFactory
     */
    final protected function getQueryFactory(): QueryFactory
    {
        if (null === $this->queryFactory) {
            $this->queryFactory = $this->doGetQueryFactory();
        }

        return $this->queryFactory;
    }

    /**
     * @return QueryFactory
     */
    protected function doGetQueryFactory(): QueryFactory
    {
        return new QueryFactory();
    }
}
