<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventSearch\Elastica;

use Elastica\Client;
use Elastica\Index;
use Elastica\IndexTemplate;
use Elastica\Mapping;
use Gdbots\Pbj\Message;
use Gdbots\Pbj\MessageResolver;
use Gdbots\Pbjx\Exception\EventSearchOperationFailed;
use Gdbots\Schemas\Pbjx\Enum\Code;
use Gdbots\Schemas\Pbjx\Mixin\Event\EventV1Mixin;
use Gdbots\Schemas\Pbjx\Mixin\Indexed\IndexedV1Mixin;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class IndexManager
{
    /**
     * Our "occurred_at" field is a 16 digit integer (seconds + 6 digits microtime)
     * In order to use elasticsearch time range queries we'll store a derived value
     * of the ISO (in UTC/ZULU) into another field.
     *
     * Generally we use "__" to indicate a derived field but kibana won't recognize it
     * and it's already been debated with no fix yet.
     *
     * @link  https://github.com/elastic/kibana/issues/2551
     * @link  https://github.com/elastic/kibana/issues/4762
     *
     * So for now, we'll use "d__" to indicate a derived field for ES.
     *
     * @const string
     */
    const OCCURRED_AT_ISO_FIELD_NAME = 'd__occurred_at_iso';

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
    protected string $indexPrefix;

    protected ?LoggerInterface $logger;

    public function __construct(string $indexPrefix, ?LoggerInterface $logger = null)
    {
        $this->indexPrefix = rtrim($indexPrefix, '-') . '-';
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * Returns the index prefix which can be used in template
     * creation or finding/updating indexes.
     *
     * @return string
     */
    public function getIndexPrefix(): string
    {
        return rtrim($this->indexPrefix, '-');
    }

    /**
     * Returns the name of the index that should be used when only
     * a date and context is available. Typically this is when
     * deleting events and you don't have a search request or
     * an event and can't derive the index from the usual methods.
     *
     * @param \DateTimeInterface $date
     * @param array              $context
     *
     * @return string
     */
    public function getIndexNameFromContext(\DateTimeInterface $date, array $context): string
    {
        return $context['index_name'] ?? $this->indexPrefix . $this->getIndexIntervalSuffix($date);
    }

    /**
     * Returns the name of the index that the event should be written to.
     *
     * @param Message $event
     *
     * @return string
     */
    public function getIndexNameForWrite(Message $event): string
    {
        /** @var \DateTimeInterface $occurredAt */
        $occurredAt = $event->get('occurred_at')->toDateTime();
        return $this->indexPrefix . $this->getIndexIntervalSuffix($occurredAt);
    }

    /**
     * Returns an array of index names (or patterns) that should be
     * queried for the given search request.
     *
     * @param Message $request
     *
     * @return string[]
     */
    public function getIndexNamesForSearch(Message $request): array
    {
        /** @var \DateTime $after */
        /** @var \DateTime $before */
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
         * being too long for a GET request (multi-index searches have index in url)
         */
        $indices = [];
        $start = clone $after;

        do {
            $indices[$this->indexPrefix . $this->getIndexIntervalSuffix($start)] = true;
            $start = $start->modify('+1 week');
        } while ($start < $end);
        $indices[$this->indexPrefix . $this->getIndexIntervalSuffix($start)] = true;

        if (count($indices) > 9) {
            $start = clone $after;
            $indices = [];
            do {
                $indices[$this->indexPrefix . $start->format('Y') . '*'] = true;
                $start = $start->modify('+1 year');
            } while ($start < $end);
        }

        return array_keys($indices);
    }

    /**
     * Returns the suffix that should be used for routing writes
     * and search requests for a given date.
     *
     * todo: make this configureable, right now we're using quarterly by default
     *
     * @param \DateTimeInterface $date
     *
     * @return string
     */
    public function getIndexIntervalSuffix(\DateTimeInterface $date): string
    {
        $quarter = ceil($date->format('n') / 3);
        return $date->format('Y') . 'q' . $quarter;
    }

    /**
     * Creates an index template in elastic search.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-templates.html
     *
     * @param Client $client
     * @param string $name
     */
    final public function updateTemplate(Client $client, string $name): void
    {
        $params = [
            'template' => '*' . $name . '*',
            'settings' => [
                'number_of_shards'   => 1,
                'number_of_replicas' => 1,
                'index'              => [
                    'analysis' => [
                        'analyzer'   => $this->getCustomAnalyzers(),
                        'normalizer' => $this->getCustomNormalizers(),
                    ],
                ],
            ],
            'mappings' => $this->createMapping()->toArray(),
        ];

        $this->beforeUpdateTemplate($params);
        $template = new IndexTemplate($client, $name);
        $template->create($params);
        $this->logger->info(sprintf('Successfully created index template [%s].', $params['template']));
    }

    /**
     * Updates an existing index settings and all of its mappings.
     * The index template handles this for any newly created indices but
     * the existing ones need to be updated directly.
     *
     * This is generally only needed for indices that you're still actively
     * writing data to.  If this fails you'll need to delete the index and
     * re-index all data for that time frame.
     *
     * The name can contain wildcards.
     *
     * @param Client $client
     * @param string $name
     */
    final public function updateIndex(Client $client, string $name): void
    {
        $index = new Index($client, $name);

        try {
            $index->setMapping($this->createMapping());
        } catch (\Throwable $e) {
            if (false !== strpos($e->getMessage(), 'no such index')) {
                $this->logger->info(sprintf('No index exists yet [%s] in ElasticSearch. Ignoring.', $name));
                return;
            }

            throw new EventSearchOperationFailed(
                sprintf('Failed to put mapping for index [%s] into ElasticSearch.', $name),
                Code::INTERNAL,
                $e
            );
        }

        $this->logger->info(sprintf('Successfully put mapping for index [%s]', $name));
        $this->updateAnalyzers($index, $name);
        $this->updateNormalizers($index, $name);
    }

    /**
     * Checks if an existing index is missing any custom analyzers
     * and if it is, updates settings to include them.
     *
     * @param Index  $index
     * @param string $name
     */
    protected function updateAnalyzers(Index $index, string $name): void
    {
        $settings = $index->getSettings();
        $customAnalyzers = $this->getCustomAnalyzers();
        $missingAnalyzers = [];

        try {
            foreach ($customAnalyzers as $id => $analyzer) {
                if (!$settings->get("analysis.analyzer.{$id}")) {
                    $missingAnalyzers[$id] = $analyzer;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Unable to read index [%s] and get analyzer settings.', $name),
                ['exception' => $e, 'index_name' => $name]
            );

            return;
        }

        if (empty($missingAnalyzers)) {
            $this->logger->info(
                sprintf(
                    'Index [%s] has all custom analyzers [%s].',
                    $name,
                    implode(',', array_keys($customAnalyzers))
                )
            );

            return;
        }

        $this->logger->warning(
            sprintf(
                'Closing index [%s] to add custom analyzers [%s].',
                $name,
                implode(',', array_keys($missingAnalyzers))
            )
        );

        try {
            $index->close();
            $index->setSettings(['analysis' => ['analyzer' => $customAnalyzers]]);
            $index->open();
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Unable to close index [%s] and update analyzer settings.', $name),
                ['exception' => $e, 'index_name' => $name]
            );

            return;
        }

        $this->logger->info(sprintf('Successfully added missing analyzers to index [%s]', $name));
    }

    /**
     * Checks if an existing index is missing any custom normalizers
     * and if it is, updates settings to include them.
     *
     * @param Index  $index
     * @param string $name
     */
    protected function updateNormalizers(Index $index, string $name): void
    {
        $settings = $index->getSettings();
        $customNormalizers = $this->getCustomNormalizers();
        $missingNormalizers = [];

        try {
            foreach ($customNormalizers as $id => $normalizer) {
                if (!$settings->get("analysis.normalizer.{$id}")) {
                    $missingNormalizers[$id] = $normalizer;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Unable to read index [%s] and get normalizer settings.', $name),
                ['exception' => $e, 'index_name' => $name]
            );

            return;
        }

        if (empty($missingNormalizers)) {
            $this->logger->info(
                sprintf(
                    'Index [%s] has all custom normalizers [%s].',
                    $name,
                    implode(',', array_keys($customNormalizers))
                )
            );

            return;
        }

        $this->logger->warning(
            sprintf(
                'Closing index [%s] to add custom normalizers [%s].',
                $name,
                implode(',', array_keys($missingNormalizers))
            )
        );

        try {
            $index->close();
            $index->setSettings(['analysis' => ['normalizer' => $missingNormalizers]]);
            $index->open();
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Unable to close index [%s] and update normalizer settings.', $name),
                ['exception' => $e, 'index_name' => $name]
            );

            return;
        }

        $this->logger->info(sprintf('Successfully added missing normalizers to index [%s]', $name));
    }

    protected function beforeUpdateTemplate(array &$params): void
    {
        // Override to customize the template params before it's pushed to elastic search.
    }

    protected function createMapping(): Mapping
    {
        $builder = $this->getMappingBuilder();
        foreach (MessageResolver::findAllUsingMixin(IndexedV1Mixin::SCHEMA_CURIE_MAJOR) as $curie) {
            $builder->addSchema(MessageResolver::resolveCurie($curie)::schema());
        }

        $mapping = $builder->build();
        $properties = $mapping->getProperties();
        $properties[self::OCCURRED_AT_ISO_FIELD_NAME] = MappingBuilder::TYPES['date'];
        $mapping->setProperties($properties);

        return $mapping;
    }

    protected function getMappingBuilder(): MappingBuilder
    {
        return new MappingBuilder();
    }

    /**
     * @link http://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-custom-analyzer.html
     *
     * @return array
     */
    protected function getCustomAnalyzers(): array
    {
        return MappingBuilder::getCustomAnalyzers();
    }

    /**
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-normalizers.html
     *
     * @return array
     */
    protected function getCustomNormalizers(): array
    {
        return MappingBuilder::getCustomNormalizers();
    }
}
