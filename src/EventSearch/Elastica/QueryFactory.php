<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventSearch\Elastica;

use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Elastica\Query\FunctionScore;
use Gdbots\Pbj\Marshaler\Elastica\MappingBuilder;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbj\Util\DateUtil;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\QueryParser\Builder\ElasticaQueryBuilder;
use Gdbots\QueryParser\Enum\BoolOperator;
use Gdbots\QueryParser\Enum\ComparisonOperator;
use Gdbots\QueryParser\Node\Field;
use Gdbots\QueryParser\Node\Numbr;
use Gdbots\QueryParser\ParsedQuery;
use Gdbots\Schemas\Pbjx\Enum\SearchEventsSort;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\SearchEventsRequest\SearchEventsRequestV1Mixin;

class QueryFactory
{
    /**
     * @param Message       $request     Search request containing pagination, date filters, etc.
     * @param ParsedQuery   $parsedQuery Parsed version of the search query (the "q" field of the request).
     * @param SchemaCurie[] $curies      An array of curies that the search should limit its search to.
     *
     * @return Query
     */
    final public function create(Message $request, ParsedQuery $parsedQuery, array $curies = []): Query
    {
        $this->applyDateFilters($request, $parsedQuery);

        $method = $request::schema()->getHandlerMethodName(false, 'for');
        if (is_callable([$this, $method])) {
            $query = $this->$method($request, $parsedQuery, $curies);
        } else {
            $query = $this->forSearchEventsRequest($request, $parsedQuery, $curies);
        }

        return Query::create($query);
    }

    protected function applyDateFilters(Message $request, ParsedQuery $parsedQuery): void
    {
        $required = BoolOperator::REQUIRED();

        $dateFilters = [
            [
                'query'    => SearchEventsRequestV1Mixin::OCCURRED_AFTER_FIELD,
                'field'    => EventV1Mixin::OCCURRED_AT_FIELD,
                'operator' => ComparisonOperator::GT()],
            [
                'query'    => SearchEventsRequestV1Mixin::OCCURRED_BEFORE_FIELD,
                'field'    => EventV1Mixin::OCCURRED_AT_FIELD,
                'operator' => ComparisonOperator::LT()],
        ];

        foreach ($dateFilters as $f) {
            if ($request->has($f['query'])) {
                $parsedQuery->addNode(
                    new Field(
                        $f['field'],
                        new Numbr(
                            (float)Microtime::fromDateTime($request->get($f['query']))->toString(),
                            $f['operator']
                        ),
                        $required
                    )
                );
            }
        }
    }

    /**
     * Add the "types" into one terms query as it's more efficient.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
     *
     * @param Message         $request
     * @param Query\BoolQuery $query
     * @param SchemaCurie[]   $curies
     */
    protected function filterCuries(Message $request, Query\BoolQuery $query, array $curies): void
    {
        if (empty($curies)) {
            return;
        }

        $types = array_map(fn(SchemaCurie $curie) => $curie->getMessage(), $curies);
        $query->addFilter(new Query\Terms(MappingBuilder::TYPE_FIELD, $types));
    }

    /**
     * Applies sorting and scoring to the query and returns the final query object
     * which will be sent to elastic search.
     *
     * @param AbstractQuery $query
     * @param Message       $request
     *
     * @return Query
     */
    protected function createSortedQuery(AbstractQuery $query, Message $request): Query
    {
        switch ($request->get(SearchEventsRequestV1Mixin::SORT_FIELD)->getValue()) {
            case SearchEventsSort::DATE_DESC:
                $query = Query::create($query);
                $query->setSort([EventV1Mixin::OCCURRED_AT_FIELD => 'desc']);
                break;

            case SearchEventsSort::DATE_ASC:
                $query = Query::create($query);
                $query->setSort([EventV1Mixin::OCCURRED_AT_FIELD => 'asc']);
                break;

            default:
                // recency scores higher
                // @link https://www.elastic.co/guide/en/elasticsearch/guide/current/decay-functions.html
                $before = $request->get(SearchEventsRequestV1Mixin::OCCURRED_BEFORE_FIELD) ?: new \DateTime('now', new \DateTimeZone('UTC'));
                $query = (new FunctionScore())
                    ->setQuery($query)
                    ->addFunction(FunctionScore::DECAY_EXPONENTIAL, [
                        IndexManager::OCCURRED_AT_ISO_FIELD_NAME => [
                            'origin' => $before->format(DateUtil::ISO8601_ZULU),
                            'scale'  => '1w',
                            'offset' => '2m',
                            'decay'  => 0.1,
                        ],
                    ]);
                $query = Query::create($query);
                break;
        }

        return $query;
    }

    protected function forSearchEventsRequest(Message $request, ParsedQuery $parsedQuery, array $curies): Query
    {
        $builder = new ElasticaQueryBuilder();
        $builder->setDefaultFieldName(MappingBuilder::ALL_FIELD)->addParsedQuery($parsedQuery);
        $query = $builder->getBoolQuery();
        $this->filterCuries($request, $query, $curies);
        return Query::create($this->createSortedQuery($query, $request));
    }
}
