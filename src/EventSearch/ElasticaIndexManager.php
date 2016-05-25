<?php

namespace Gdbots\Pbjx\EventSearch;

use Elastica\Client;
use Elastica\Index;
use Elastica\IndexTemplate;
use Elastica\Type;
use Elastica\Type\Mapping;
use Gdbots\Common\Microtime;
use Gdbots\Common\Util\ClassUtils;
use Gdbots\Pbj\Marshaler\Elastica\MappingFactory;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbj\Schema;
use Gdbots\Pbjx\Exception\EventSearchOperationFailed;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Indexed\IndexedV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\SearchEventsRequest\SearchEventsRequest;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ElasticaIndexManager
{
    /**
     * Our "occurred_at" field is a 16 digit integer (seconds + 6 digits microtime)
     * In order to use elasticsearch time range queries we'll store a derived value
     * of the ISO (in UTC/ZULU) into another field.
     *
     * @const string
     */
    const OCCURRED_AT_ISO_FIELD_NAME = '__occurred_at_iso';

    /**
     * The name of the index (without any time interval) that all events
     * will be written to and searched in.
     *
     * Indexes are prefix-YYYYq# by default.  By extending this class you
     * can customize the prefix for multi-tenant applications.
     * e.g. client1-prefix-yyyymm.
     *
     * Configurable intervals are coming soon (quarterly, monthly, daily)
     *
     * @var string
     */
    protected $indexPrefix;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @param string $indexPrefix
     * @param LoggerInterface|null $logger
     */
    public function __construct($indexPrefix, LoggerInterface $logger = null)
    {
        $this->indexPrefix = rtrim($indexPrefix, '-') . '-';
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * Returns the name of the index that the event should be written to.
     *
     * @param Event $event
     *
     * @return string
     */
    public function getIndexNameForWrite(Event $event)
    {
        /** @var \DateTime $occurredAt */
        $occurredAt = $event->get('occurred_at')->toDateTime();
        return $this->indexPrefix . $this->getIndexIntervalSuffix($occurredAt);
    }

    /**
     * Returns an array of index names (or patterns) that should be
     * queried for the given search request.
     *
     * @param SearchEventsRequest $request
     *
     * @return string[]
     */
    public function getIndexNamesForSearch(SearchEventsRequest $request)
    {
        /** @var \DateTime $after ,$before */
        $after = $request->get('occurred_after');
        $before = $request->get('occurred_before');

        /*
         * when no lower bound is used, we must assume they meant to search
         * all events to the beginning of time.  this could take forever.
         */
        if (null === $after) {
            return [$this->indexPrefix . '*'];
        }

        // if no upper bound is present, make one up
        $end = $before ?: new \DateTime('now', new \DateTimeZone('UTC'));

        /*
         * now, while start is less than end... accumulate the intervals as indices
         * if the span results in too many indices, use a wildcard to prevent urls
         * being too long for a GET request (multi-indice searchs have index in url)
         */
        $indices = [];
        $start = clone $after;

        do {
            $indices[$this->indexPrefix . $this->getIndexIntervalSuffix($start)] = true;
            $start->modify('+1 month');
        } while ($start < $end);

        if (count($indices) > 9) {
            $start = clone $after;
            $indices = [];
            do {
                $indices[$this->indexPrefix . $start->format('Y') . '*'] = true;
                $start->modify('+1 year');
            } while ($start < $end);
        }

        return array_keys($indices);
    }

    /**
     * Creates an index template in elastic search.
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-templates.html
     *
     * @param Client $client
     * @param string $name
     */
    final public function updateTemplate(Client $client, $name)
    {
        $fakeIndex = new Index($client, $name);
        $mappings = [];
        foreach ($this->createMappings() as $typeName => $mapping) {
            $mapping->setType(new Type($fakeIndex, $typeName));
            $mappings[$typeName] = $mapping->toArray();
        }

        $params = [
            'template' => '*' . $name . '*',
            'settings' => [
                'index' => [
                    'analysis' => [
                        'analyzer' => MappingFactory::getCustomAnalyzers()
                    ]
                ]
            ],
            'mappings' => $mappings
        ];

        $this->beforeUpdateTemplate($params);
        $template = new IndexTemplate($client, $name);
        $template->create($params);
    }

    /**
     * @param array $params
     */
    protected function beforeUpdateTemplate(array &$params)
    {
        // Override to customize the template params before it's pushed to elastic search.
    }

    /**
     * Returns the suffix that should be used for routing writes
     * and search requests for a given date.
     *
     * todo: make this configureable, right now we're using quarterly by default
     *
     * @param \DateTime $date
     *
     * @return string
     */
    protected function getIndexIntervalSuffix(\DateTime $date)
    {
        $quarter = ceil($date->format('n') / 3);
        return $date->format('Y') . 'q' . $quarter;
    }

    /**
     * @param Schema $schema
     * @param Mapping $mapping
     */
    protected function filterMapping(Schema $schema, Mapping $mapping)
    {
        /*
         * Override to customize the mapping before it's pushed to elastic search.
         *
         * If a method "filterMappingFor$CamelizedSchemaName" exists, it will
         * also be called with the same signature.
         *
         */
    }

    /**
     * @return Mapping[]
     */
    private function createMappings()
    {
        $schemas = MessageResolver::findAllUsingMixin(EventV1Mixin::create());
        $mappingFactory = new MappingFactory();
        $mappings = [];

        foreach ($schemas as $schema) {
            $this->logger->info(sprintf('Creating mapping for [%s] => [%s]', $schema->getId(), $schema->getClassName()));

            $mapping = $mappingFactory->create($schema, 'english');
            $properties = $mapping->getProperties();
            $properties[self::OCCURRED_AT_ISO_FIELD_NAME] = ['type' => 'date', 'include_in_all' => false];
            $mapping->enableAllField(false)->setProperties($properties);

            $dynamicTemplates = $mapping->getParam('dynamic_templates');
            if (!empty($dynamicTemplates)) {
                $mapping->setParam('dynamic_templates', $dynamicTemplates);
            }

            $this->filterMapping($schema, $mapping);
            $method = 'filterMappingFor' . ucfirst($schema->getHandlerMethodName(false));
            if (is_callable([$this, $method])) {
                $this->$method($schema, $mapping);
            }

            $mappings[$schema->getCurie()->getMessage()] = $mapping;
        }

        return $mappings;
    }
}
