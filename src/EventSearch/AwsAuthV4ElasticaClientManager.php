<?php

namespace Gdbots\Pbjx\EventSearch;

use Aws\Credentials\Credentials;
use Psr\Log\LoggerInterface;

class AwsAuthV4ElasticaClientManager extends ElasticaClientManager
{
    /** @var Credentials */
    protected $credentials;

    /** @var string */
    protected $region;

    /**
     * @param Credentials $credentials
     * @param string $region
     * @param array $clusters
     * @param LoggerInterface $logger
     */
    public function __construct(Credentials $credentials, $region, array $clusters, LoggerInterface $logger = null)
    {
        parent::__construct($clusters, $logger);
        $this->credentials = $credentials;
        $this->region = $region;
    }

    /**
     * @param string $cluster
     * @param array $config
     *
     * @return array
     */
    protected function configureCluster($cluster, array $config)
    {
        $config['transport'] = 'AwsAuthV4';
        $config['aws_access_key_id'] = $this->credentials->getAccessKeyId();
        $config['aws_secret_access_key'] = $this->credentials->getSecretKey();
        $config['aws_region'] = $this->region;
        return $config;
    }
}
