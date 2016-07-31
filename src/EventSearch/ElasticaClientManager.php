<?php

namespace Gdbots\Pbjx\EventSearch;

use Elastica\Client;
use Gdbots\Pbjx\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class ElasticaClientManager
{
    /**
     * An array of clusters keyed by a name.  This factory will create the elastica
     * client for a given cluster using the settings in the key.
     *
     * The reason for the "clusters" (not just single cluster) config is to make it
     * possible to manage a multi-tenant app or run different clusters for different
     * parts of the same app (logging vs content, etc.).
     *
     *  [
     *      'default' => [
     *          'round_robin' => true,
     *          'timeout' => 300,
     *          'debug' => false,
     *          'persistent' => true
     *          'servers' => [
     *              ['host' => 'localhost', 'port' => 9200]
     *          ]
     *      ],
     *      'client1' => [...],
     *      'client2' => [...],
     *      'logging' => [...],
     *  ]
     *
     * @var array
     */
    protected $clusters;

    /** @var LoggerInterface */
    protected $logger;

    /** @var Client[] */
    protected $clients = [];

    /**
     * @param array $clusters
     * @param LoggerInterface $logger
     */
    public function __construct(array $clusters, LoggerInterface $logger = null)
    {
        $this->clusters = $clusters;
        $this->logger = $logger;
    }

    /**
     * Gets an elastica client using the config from the provided cluster name.
     *
     * All calls with the same cluster name will return the same client instance.
     *
     * @param string $cluster
     * @return Client
     */
    public function getClient($cluster = 'default')
    {
        if (isset($this->clients[$cluster])) {
            return $this->clients[$cluster];
        }

        if (!$this->hasCluster($cluster)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The cluster [%s] is not one of the available clusters [%s].',
                    $cluster,
                    implode(',', $this->getAvailableClusters())
                )
            );
        }

        $config = $this->clusters[$cluster];

        if (isset($config['debug'])) {
            $config['log'] = filter_var($config['debug'], FILTER_VALIDATE_BOOLEAN);
            unset($config['debug']);
        } else {
            $config['log'] = false;
        }

        if (isset($config['round_robin'])) {
            $config['roundRobin'] = filter_var($config['round_robin'], FILTER_VALIDATE_BOOLEAN);
            unset($config['round_robin']);
        }

        $config = $this->configureCluster($cluster, $config);
        $servers = $config['servers'];
        $configuredServers = [];
        unset($config['servers']);

        foreach ($servers as $server) {
            $configuredServers[] = array_merge($config, $server);
        }

        $config['servers'] = $configuredServers;
        return $this->clients[$cluster] = new Client($config, null, $config['log'] ? $this->logger : null);
    }

    /**
     * @param string $cluster
     * @return bool
     */
    public function hasCluster($cluster)
    {
        return isset($this->clusters[$cluster]);
    }

    /**
     * Returns the names of the available clusters.
     *
     * @return string[]
     */
    public function getAvailableClusters()
    {
        return array_keys($this->clusters);
    }

    /**
     * @param string $cluster
     * @param array $config
     *
     * @return array
     */
    protected function configureCluster($cluster, array $config)
    {
        return $config;
    }
}
