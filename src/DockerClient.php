<?php

declare(strict_types=1);

namespace Docker\API;

use Docker\API\Client\ContainerClient;
use Docker\API\Client\ImageClient;
use Docker\API\Client\NetworkClient;
use Docker\API\Client\VolumeClient;
use Docker\API\Client\SystemClient;
use Docker\API\Client\ExecClient;
use Docker\API\Client\SwarmClient;
use Docker\API\Client\NodeClient;
use Docker\API\Client\ServiceClient;
use Docker\API\Client\TaskClient;
use Docker\API\Client\SecretClient;
use Docker\API\Client\ConfigClient;
use Docker\API\Client\PluginClient;
use Docker\API\Http\HttpClient;
use Docker\API\Exception\DockerException;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Docker Engine API v1.45 PHP SDK Client
 * 
 * This is the main entry point for interacting with Docker Engine API.
 * It provides access to all Docker API endpoints through specialized client classes.
 */
class DockerClient
{
    private HttpClient $httpClient;
    private ContainerClient $containers;
    private ImageClient $images;
    private NetworkClient $networks;
    private VolumeClient $volumes;
    private SystemClient $system;
    private ExecClient $exec;
    private SwarmClient $swarm;
    private NodeClient $nodes;
    private ServiceClient $services;
    private TaskClient $tasks;
    private SecretClient $secrets;
    private ConfigClient $configs;
    private PluginClient $plugins;

    public function __construct(
        string $baseUri = 'unix:///var/run/docker.sock',
        array $options = [],
        ?LoggerInterface $logger = null
    ) {
        $this->httpClient = new HttpClient(
            $this->createGuzzleClient($baseUri, $options),
            $logger ?? new NullLogger()
        );

        $this->initializeClients();
    }

    /**
     * Create Guzzle HTTP client with Docker-specific configuration
     */
    private function createGuzzleClient(string $baseUri, array $options): GuzzleClient
    {
        $defaultOptions = [
            'timeout' => 60,
            'connect_timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        // Handle Unix socket connection
        if (str_starts_with($baseUri, 'unix://')) {
            $socketPath = substr($baseUri, 7);
            $defaultOptions['curl'] = [
                CURLOPT_UNIX_SOCKET_PATH => $socketPath,
            ];
            $baseUri = 'http://localhost/v1.45';
        } elseif (!str_contains($baseUri, '/v1.45')) {
            $baseUri = rtrim($baseUri, '/') . '/v1.45';
        }

        $options = array_merge($defaultOptions, $options, ['base_uri' => $baseUri]);

        return new GuzzleClient($options);
    }

    /**
     * Initialize all API client instances
     */
    private function initializeClients(): void
    {
        $this->containers = new ContainerClient($this->httpClient);
        $this->images = new ImageClient($this->httpClient);
        $this->networks = new NetworkClient($this->httpClient);
        $this->volumes = new VolumeClient($this->httpClient);
        $this->system = new SystemClient($this->httpClient);
        $this->exec = new ExecClient($this->httpClient);
        $this->swarm = new SwarmClient($this->httpClient);
        $this->nodes = new NodeClient($this->httpClient);
        $this->services = new ServiceClient($this->httpClient);
        $this->tasks = new TaskClient($this->httpClient);
        $this->secrets = new SecretClient($this->httpClient);
        $this->configs = new ConfigClient($this->httpClient);
        $this->plugins = new PluginClient($this->httpClient);
    }

    /**
     * Get container operations client
     */
    public function containers(): ContainerClient
    {
        return $this->containers;
    }

    /**
     * Get image operations client
     */
    public function images(): ImageClient
    {
        return $this->images;
    }

    /**
     * Get network operations client
     */
    public function networks(): NetworkClient
    {
        return $this->networks;
    }

    /**
     * Get volume operations client
     */
    public function volumes(): VolumeClient
    {
        return $this->volumes;
    }

    /**
     * Get system operations client
     */
    public function system(): SystemClient
    {
        return $this->system;
    }

    /**
     * Get exec operations client
     */
    public function exec(): ExecClient
    {
        return $this->exec;
    }

    /**
     * Get swarm operations client
     */
    public function swarm(): SwarmClient
    {
        return $this->swarm;
    }

    /**
     * Get node operations client
     */
    public function nodes(): NodeClient
    {
        return $this->nodes;
    }

    /**
     * Get service operations client
     */
    public function services(): ServiceClient
    {
        return $this->services;
    }

    /**
     * Get task operations client
     */
    public function tasks(): TaskClient
    {
        return $this->tasks;
    }

    /**
     * Get secret operations client
     */
    public function secrets(): SecretClient
    {
        return $this->secrets;
    }

    /**
     * Get config operations client
     */
    public function configs(): ConfigClient
    {
        return $this->configs;
    }

    /**
     * Get plugin operations client
     */
    public function plugins(): PluginClient
    {
        return $this->plugins;
    }

    /**
     * Get the underlying HTTP client
     */
    public function getHttpClient(): HttpClient
    {
        return $this->httpClient;
    }

    /**
     * Test connection to Docker daemon
     */
    public function ping(): bool
    {
        try {
            $this->system->ping();
            return true;
        } catch (DockerException $e) {
            return false;
        }
    }
}