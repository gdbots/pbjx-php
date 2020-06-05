<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\EventSearch\Elastica;

use Aws\Credentials\CredentialsInterface;
use Psr\Log\LoggerInterface;

class AwsAuthV4ClientManager extends ClientManager
{
    protected CredentialsInterface $credentials;
    protected string $region;

    public function __construct(CredentialsInterface $credentials, string $region, array $clusters, ?LoggerInterface $logger = null)
    {
        parent::__construct($clusters, $logger);
        $this->credentials = $credentials;
        $this->region = $region;
    }

    protected function configureCluster(string $cluster, array $config): array
    {
        $config['transport'] = 'AwsAuthV4';
        $config['aws_access_key_id'] = $this->credentials->getAccessKeyId();
        $config['aws_secret_access_key'] = $this->credentials->getSecretKey();
        $config['aws_session_token'] = $this->credentials->getSecurityToken();
        $config['aws_region'] = $this->region;
        return $config;
    }
}
