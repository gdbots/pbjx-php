<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventSearch\Elastica;

use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Elastica\Query\FunctionScore;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\Util\DateUtil;
use Gdbots\Pbj\WellKnown\Microtime;
use Gdbots\QueryParser\Builder\ElasticaQueryBuilder;
use Gdbots\QueryParser\Enum\BoolOperator;
use Gdbots\QueryParser\Enum\ComparisonOperator;
use Gdbots\QueryParser\Node\Field;
use Gdbots\QueryParser\Node\Numbr;
use Gdbots\QueryParser\ParsedQuery;
use Gdbots\Schemas\Pbjx\Enum\SearchEventsSort;

class QueryFactory
{
    final public function create(Message $request, ParsedQuery $parsedQuery): Query
    {
        $this->applyDateFilters($request, $parsedQuery);

        $method = $request::schema()->getHandlerMethodName(false, 'for');
        if (is_callable([$this, $method])) {
            $query = $this->$method($request, $parsedQuery);
        } else {
            $query = $this->forSearchEventsRequest($request, $parsedQuery);
        }

        return Query::create($query);
    }

    protected function applyDateFilters(Message $request, ParsedQuery $parsedQuery): void
    {
        $required = BoolOperator::REQUIRED();

        $dateFilters = [
            ['query' => 'occurred_after', 'field' => 'occurred_at', 'operator' => ComparisonOperator::GT()],
            ['query' => 'occurred_before', 'field' => 'occurred_at', 'operator' => ComparisonOperator::LT()],
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
        switch ($request->get('sort')->getValue()) {
            case SearchEventsSort::DATE_DESC:
                $query = Query::create($query);
                $query->setSort(['occurred_at' => 'desc']);
                break;

            case SearchEventsSort::DATE_ASC:
                $query = Query::create($query);
                $query->setSort(['occurred_at' => 'asc']);
                break;

            default:
                // recency scores higher
                // @link https://www.elastic.co/guide/en/elasticsearch/guide/current/decay-functions.html
                $before = $request->get('occurred_before') ?: new \DateTime('now', new \DateTimeZone('UTC'));
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

    protected function forSearchEventsRequest(Message $request, ParsedQuery $parsedQuery): Query
    {
        $builder = new ElasticaQueryBuilder();
        $builder->setDefaultFieldName('_all')->addParsedQuery($parsedQuery);
        $query = $builder->getBoolQuery();
        return Query::create($this->createSortedQuery($query, $request));
    }
}
