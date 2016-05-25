<?php

namespace Gdbots\Pbjx\EventSearch;

use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\QueryParser\ParsedQuery;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\SearchEventsRequest\SearchEventsRequest;
use Gdbots\Schemas\Pbjx\Mixin\SearchEventsResponse\SearchEventsResponse;

interface EventSearch
{
    /**
     * Adds an array of events to the search index.
     *
     * @param Event[] $events
     * @throws GdbotsPbjxException
     */
    public function index(array $events);

    /**
     * Executes a search request and populates the provided response object with
     * the events found, total, time_taken, etc.
     *
     * @param SearchEventsRequest $request
     * @param ParsedQuery $parsedQuery
     * @param SearchEventsResponse $response
     *
     * @return SearchEventsResponse
     *
     * @throws GdbotsPbjxException
     */
    public function search(SearchEventsRequest $request, ParsedQuery $parsedQuery, SearchEventsResponse $response);
}
