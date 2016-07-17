<?php

namespace Gdbots\Pbjx\EventSearch;

use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\QueryParser\ParsedQuery;
use Gdbots\Schemas\Pbjx\Mixin\Indexed\Indexed;
use Gdbots\Schemas\Pbjx\Mixin\SearchEventsRequest\SearchEventsRequest;
use Gdbots\Schemas\Pbjx\Mixin\SearchEventsResponse\SearchEventsResponse;

interface EventSearch
{
    /**
     * Adds an array of events to the search index.
     *
     * @param Indexed[] $events
     * @throws GdbotsPbjxException
     */
    public function index(array $events);

    /**
     * Executes a search request and populates the provided response object with
     * the events found, total, time_taken, etc.
     *
     * @param SearchEventsRequest $request      Search request containing pagination, date filters, etc.
     * @param ParsedQuery $parsedQuery          Parsed version of the search query (the "q" field of the request).
     * @param SearchEventsResponse $response    Results from search will be added to this object.
     * @param SchemaCurie[] $curies             An array of curies that the search should limit its search to.
     *                                          If empty, it will search all events in the index.
     *
     * @throws GdbotsPbjxException
     */
    public function search(
        SearchEventsRequest $request,
        ParsedQuery $parsedQuery,
        SearchEventsResponse $response,
        array $curies = []
    );
}
