<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventSearch;

use Gdbots\Pbj\Message;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Pbj\WellKnown\Identifier;
use Gdbots\Pbjx\Exception\GdbotsPbjxException;
use Gdbots\QueryParser\ParsedQuery;

interface EventSearch
{
    /**
     * Creates the storage for the EventSearch.
     *
     * @param array $context Data that helps the implementation decide where to create the storage.
     */
    public function createStorage(array $context = []): void;

    /**
     * Returns debugging information about the storage for the EventSearch.
     *
     * @param array $context Data that helps the implementation decide what storage to describe.
     *
     * @return string
     */
    public function describeStorage(array $context = []): string;

    /**
     * Adds an array of events to the search index.
     *
     * @param Message[] $events  An array of events to add to the search index.
     * @param array     $context Data that helps the EventSearch decide where to read/write data from.
     *
     * @throws GdbotsPbjxException
     */
    public function indexEvents(array $events, array $context = []): void;

    /**
     * Deletes an array events by their identifiers (the "event_id" field on the event).
     *
     * @param Identifier[] $eventIds An array of event ids to delete from the search index.
     * @param array        $context  Data that helps the EventStore decide where to delete data from.
     *
     * @throws GdbotsPbjxException
     */
    public function deleteEvents(array $eventIds, array $context = []): void;

    /**
     * Executes a search request and populates the provided response object with
     * the events found, total, time_taken, etc.
     *
     * @param Message       $request     Search request containing pagination, date filters, etc.
     * @param ParsedQuery   $parsedQuery Parsed version of the search query (the "q" field of the request).
     * @param Message       $response    Results from search will be added to this object.
     * @param SchemaCurie[] $curies      An array of curies that the search should limit its search to.
     *                                   If empty, it will search all events in the index.
     * @param array         $context     Data that helps the EventSearch decide where to read/write data from.
     *
     * @throws GdbotsPbjxException
     */
    public function searchEvents(Message $request, ParsedQuery $parsedQuery, Message $response, array $curies = [], array $context = []): void;
}
