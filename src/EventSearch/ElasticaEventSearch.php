<?php

namespace Gdbots\Pbjx\EventSearch;

use Elastica\Document;
use Elastica\Query;
use Elastica\Query\FunctionScore;
use Elastica\Result;
use Elastica\ResultSet;
use Elastica\Search;
use Gdbots\Common\Microtime;
use Gdbots\Common\Util\ClassUtils;
use Gdbots\Common\Util\DateUtils;
use Gdbots\Common\Util\NumberUtils;
use Gdbots\Pbj\Marshaler\Elastica\DocumentMarshaler;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\Exception\EventSearchOperationFailed;
use Gdbots\QueryParser\Builder\ElasticaQueryBuilder;
use Gdbots\QueryParser\Enum\BoolOperator;
use Gdbots\QueryParser\Enum\ComparisonOperator;
use Gdbots\QueryParser\Node\Field;
use Gdbots\QueryParser\Node\Numbr;
use Gdbots\QueryParser\ParsedQuery;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Enum\SearchSort;
use Gdbots\Schemas\Pbjx\Mixin\Indexed\Indexed;
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

    /** @var ElasticaQueryBuilder */
    protected $queryBuilder;

    /**
     * Used to limit the amount of time a query can take.
     * @var string
     */
    protected $timeout;

    /**
     * @param ElasticaClientManager $clientManager
     * @param ElasticaIndexManager $indexManager
     * @param LoggerInterface|null $logger
     * @param string $timeout
     */
    public function __construct(
        ElasticaClientManager $clientManager,
        ElasticaIndexManager $indexManager,
        LoggerInterface $logger = null,
        $timeout = null
    ) {
        $this->clientManager = $clientManager;
        $this->indexManager = $indexManager;
        $this->logger = $logger ?: new NullLogger();
        $this->timeout = $timeout ?: '100ms';
        $this->marshaler = new DocumentMarshaler();
        $this->queryBuilder = new ElasticaQueryBuilder();
    }

    /**
     * {@inheritdoc}
     * @param Indexed[] $events
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
    final public function search(
        SearchEventsRequest $request,
        ParsedQuery $parsedQuery,
        SearchEventsResponse $response,
        array $curies = []
    ) {
        $search = new Search($this->getClientForSearch($request));
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
            Search::OPTION_TIMEOUT => $this->timeout,
            Search::OPTION_FROM => $offset,
            Search::OPTION_SIZE => $perPage,
            Search::OPTION_SEARCH_IGNORE_UNAVAILABLE => true,
        ];

        $required = BoolOperator::REQUIRED();

        // fixme: something funky about date filters, use raw 'occurred_at' field for now.
        if ($request->has('occurred_after')) {
            $parsedQuery->addNode(
                new Field(
                    'occurred_at',
                    new Numbr(
                        Microtime::fromDateTime($request->get('occurred_after'))->toString(),
                        ComparisonOperator::GT()
                    ),
                    $required
                )
            );
        }

        if ($request->has('occurred_before')) {
            $parsedQuery->addNode(
                new Field(
                    'occurred_at',
                    new Numbr(
                        Microtime::fromDateTime($request->get('occurred_before'))->toString(),
                        ComparisonOperator::LT()
                    ),
                    $required
                )
            );
        }

        try {
            $search->setOptionsAndQuery($options, $this->createQuery($request, $parsedQuery));
            $this->beforeSearch($search, $request);
            $results = $search->search();
        } catch (\Exception $e) {
            $this->logger->error(
                'ElasticSearch query [{query}] failed.',
                [
                    'exception' => $e,
                    'pbj_schema' => $request->schema()->getId()->toString(),
                    'pbj' => $request->toArray(),
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
                $events[] = $this->unmarshalResult($result);
            } catch (\Exception $e) {
                $this->logger->error(
                    'Source returned from ElasticSearch could not be unmarshaled.',
                    [
                        'exception' => $e,
                        'hit' => $result->getHit(),
                    ]
                );
            }
        }

        $response
            ->set('total', $results->getTotalHits())
            ->set('has_more', ($offset + $perPage) < $results->getTotalHits() && $offset < 1000)
            ->set('time_taken', (int) $results->getTotalTime())
            ->set('max_score', (float) $results->getMaxScore())
            ->addToList('results', $events);

        $this->afterSearch($results, $response);
        return $response;
    }

    /**
     * Returns an elastica client for a WRITE operation based on the incoming message.
     * The message is provided so you can dynamically decide which cluster to use
     * based on the message content itself.  (e.g. for multi-tenant apps)
     *
     * @param Indexed $event
     * @return \Elastica\Client
     */
    protected function getClientForWrite(Indexed $event)
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
     * @param Indexed $event
     */
    protected function beforeIndex(Document $document, Indexed $event)
    {
        // Override to customize the document before it is indexed.
    }

    /**
     * @param Search $search
     * @param SearchEventsRequest $request
     */
    protected function beforeSearch(Search $search, SearchEventsRequest $request)
    {
        // Override to customize the search before it is executed.
    }

    /**
     * @param ResultSet $results
     * @param SearchEventsResponse $response
     */
    protected function afterSearch(ResultSet $results, SearchEventsResponse $response)
    {
        // Override to customize the response before it is returned.
    }

    /**
     * @param Result $result
     * @return Indexed
     */
    protected function unmarshalResult(Result $result)
    {
        return $this->marshaler->unmarshal($result->getSource());
    }

    /**
     * @param SearchEventsRequest $request
     * @param ParsedQuery $parsedQuery
     *
     * @return Query
     */
    protected function createQuery(SearchEventsRequest $request, ParsedQuery $parsedQuery)
    {
        $this->queryBuilder->setDefaultFieldName('_all');
        $query = $this->queryBuilder->addParsedQuery($parsedQuery)->getBoolQuery();
        return Query::create($this->createSortedQuery($query, $request));
    }

    /**
     * Applies sorting and scoring to the query and returns the final query object
     * which will be sent to elastic search.
     *
     * @param Query\AbstractQuery $query
     * @param SearchEventsRequest $request
     *
     * @return Query
     */
    protected function createSortedQuery(Query\AbstractQuery $query, SearchEventsRequest $request)
    {
        switch ($request->get('sort')->getValue()) {
            case SearchSort::DATE_DESC:
                $query = Query::create($query);
                $query->setSort(['occurred_at' => 'desc']);
                break;

            case SearchSort::DATE_ASC:
                $query = Query::create($query);
                $query->setSort(['occurred_at' => 'asc']);
                break;

            default:
                // example custom scoring (recency scores higher)
                /*
                $before = $request->get('occurred_before') ?: new \DateTime('now', new \DateTimeZone('UTC'));
                $query = (new FunctionScore())
                    ->setQuery($query)
                    ->addFunction(FunctionScore::DECAY_EXPONENTIAL, [
                        ElasticaIndexManager::OCCURRED_AT_ISO_FIELD_NAME => [
                            'origin' => $before->format('U'),
                            'scale' => '1d',
                            'offset' => '1w',
                            'decay' => 0.25
                        ]
                    ]);
                break;
                */
                $query = Query::create($query);
                break;
        }

        return $query;
    }
}
