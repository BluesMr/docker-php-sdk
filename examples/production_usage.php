<?php

/**
 * Production-grade Docker API SDK usage example
 * 
 * This example demonstrates professional usage patterns including:
 * - Proper error handling and validation
 * - Resource management and cleanup
 * - Logging and monitoring
 * - Configuration management
 * - Performance optimization
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Docker\API\DockerClient;
use Docker\API\Model\Container\ContainerCreateRequest;
use Docker\API\Model\Container\ContainerUpdateRequest;
use Docker\API\Exception\DockerException;
use Docker\API\Exception\ClientException;
use Docker\API\Exception\ServerException;
use Docker\API\Exception\InvalidParameterException;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

class ProductionDockerManager
{
    private DockerClient $docker;
    private Logger $logger;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'docker_host' => 'unix:///var/run/docker.sock',
            'timeout' => 120,
            'connect_timeout' => 30,
            'log_level' => Logger::INFO,
            'log_file' => 'logs/docker-manager.log',
            'max_retries' => 3,
            'retry_delay' => 1000000, // microseconds
        ], $config);

        $this->setupLogging();
        $this->setupDockerClient();
        $this->validateConnection();
    }

    private function setupLogging(): void
    {
        $this->logger = new Logger('docker-manager');
        
        // Console handler for immediate feedback
        $this->logger->pushHandler(
            new StreamHandler('php://stdout', $this->config['log_level'])
        );
        
        // File handler for persistent logging
        $this->logger->pushHandler(
            new RotatingFileHandler(
                $this->config['log_file'],
                0, // Keep all files
                Logger::DEBUG
            )
        );
    }

    private function setupDockerClient(): void
    {
        try {
            $this->docker = new DockerClient(
                $this->config['docker_host'],
                [
                    'timeout' => $this->config['timeout'],
                    'connect_timeout' => $this->config['connect_timeout'],
                    'headers' => [
                        'User-Agent' => 'ProductionDockerManager/1.0'
                    ]
                ],
                $this->logger
            );
            
            $this->logger->info('Docker client initialized', [
                'host' => $this->config['docker_host']
            ]);
            
        } catch (Exception $e) {
            $this->logger->critical('Failed to initialize Docker client', [
                'error' => $e->getMessage(),
                'host' => $this->config['docker_host']
            ]);
            throw $e;
        }
    }

    private function validateConnection(): void
    {
        try {
            if (!$this->docker->ping()) {
                throw new DockerException('Docker daemon is not responding');
            }
            
            $info = $this->docker->system()->info();
            $this->logger->info('Docker connection validated', [
                'version' => $info['ServerVersion'],
                'containers' => $info['Containers'],
                'images' => $info['Images']
            ]);
            
        } catch (DockerException $e) {
            $this->logger->critical('Docker connection validation failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Deploy a web application with full production configuration
     */
    public function deployWebApplication(array $config): array
    {
        $this->logger->info('Starting web application deployment', $config);
        
        try {
            // Validate configuration
            $this->validateDeploymentConfig($config);
            
            // Create network if it doesn't exist
            $networkId = $this->ensureNetwork($config['network']);
            
            // Create volume if it doesn't exist
            $volumeId = $this->ensureVolume($config['volume']);
            
            // Pull latest image
            $this->pullImage($config['image']);
            
            // Create and start container
            $containerId = $this->createWebContainer($config, $networkId, $volumeId);
            
            // Wait for container to be healthy
            $this->waitForHealthy($containerId, $config['health_check_timeout'] ?? 60);
            
            // Configure load balancer or reverse proxy
            if (isset($config['load_balancer'])) {
                $this->configureLoadBalancer($containerId, $config['load_balancer']);
            }
            
            $result = [
                'container_id' => $containerId,
                'network_id' => $networkId,
                'volume_id' => $volumeId,
                'status' => 'deployed'
            ];
            
            $this->logger->info('Web application deployed successfully', $result);
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Web application deployment failed', [
                'error' => $e->getMessage(),
                'config' => $config
            ]);
            
            // Cleanup on failure
            $this->cleanupFailedDeployment($config);
            throw $e;
        }
    }

    private function validateDeploymentConfig(array $config): void
    {
        $required = ['name', 'image', 'network', 'volume'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new InvalidParameterException("Required field '{$field}' is missing");
            }
        }
        
        // Validate container name format
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $config['name'])) {
            throw new InvalidParameterException('Invalid container name format');
        }
    }

    private function ensureNetwork(array $networkConfig): string
    {
        try {
            // Try to inspect existing network
            $network = $this->docker->networks()->inspect($networkConfig['name']);
            $this->logger->debug('Using existing network', ['id' => $network['Id']]);
            return $network['Id'];
            
        } catch (ClientException $e) {
            // Network doesn't exist, create it
            $this->logger->info('Creating new network', $networkConfig);
            
            $network = $this->docker->networks()->create([
                'Name' => $networkConfig['name'],
                'Driver' => $networkConfig['driver'] ?? 'bridge',
                'IPAM' => $networkConfig['ipam'] ?? [
                    'Config' => [
                        [
                            'Subnet' => '172.20.0.0/16',
                            'Gateway' => '172.20.0.1'
                        ]
                    ]
                ],
                'Labels' => array_merge([
                    'managed-by' => 'ProductionDockerManager',
                    'created-at' => date('c')
                ], $networkConfig['labels'] ?? [])
            ]);
            
            return $network['Id'];
        }
    }

    private function ensureVolume(array $volumeConfig): string
    {
        try {
            // Try to inspect existing volume
            $volume = $this->docker->volumes()->inspect($volumeConfig['name']);
            $this->logger->debug('Using existing volume', ['name' => $volume['Name']]);
            return $volume['Name'];
            
        } catch (ClientException $e) {
            // Volume doesn't exist, create it
            $this->logger->info('Creating new volume', $volumeConfig);
            
            $volume = $this->docker->volumes()->create([
                'Name' => $volumeConfig['name'],
                'Driver' => $volumeConfig['driver'] ?? 'local',
                'DriverOpts' => $volumeConfig['driver_opts'] ?? [],
                'Labels' => array_merge([
                    'managed-by' => 'ProductionDockerManager',
                    'created-at' => date('c')
                ], $volumeConfig['labels'] ?? [])
            ]);
            
            return $volume['Name'];
        }
    }

    private function pullImage(string $image): void
    {
        $this->logger->info('Pulling image', ['image' => $image]);
        
        $pullStream = $this->docker->images()->create($image);
        
        while (!$pullStream->eof()) {
            $line = $pullStream->read(1024);
            if ($line) {
                $data = json_decode(trim($line), true);
                if ($data && isset($data['status'])) {
                    $this->logger->debug('Pull progress', [
                        'image' => $image,
                        'status' => $data['status']
                    ]);
                }
            }
        }
        
        $this->logger->info('Image pulled successfully', ['image' => $image]);
    }

    private function createWebContainer(array $config, string $networkId, string $volumeId): string
    {
        $containerConfig = new ContainerCreateRequest($config['image']);
        
        // Basic configuration
        $containerConfig
            ->setHostname($config['hostname'] ?? $config['name'])
            ->setWorkingDir($config['working_dir'] ?? '/app')
            ->setTty(false)
            ->setOpenStdin(false);
        
        // Environment variables
        if (isset($config['environment'])) {
            foreach ($config['environment'] as $key => $value) {
                $containerConfig->addEnv($key, $value);
            }
        }
        
        // Labels
        $containerConfig->addLabel('managed-by', 'ProductionDockerManager');
        $containerConfig->addLabel('deployed-at', date('c'));
        $containerConfig->addLabel('version', $config['version'] ?? '1.0');
        
        if (isset($config['labels'])) {
            foreach ($config['labels'] as $key => $value) {
                $containerConfig->addLabel($key, $value);
            }
        }
        
        // Exposed ports
        if (isset($config['ports'])) {
            $containerConfig->setExposedPorts($config['ports']);
        }
        
        // Health check
        if (isset($config['health_check'])) {
            $containerConfig->setHostConfig([
                'HealthCheck' => $config['health_check']
            ]);
        }
        
        // Host configuration
        $hostConfig = [
            'RestartPolicy' => [
                'Name' => $config['restart_policy'] ?? 'unless-stopped'
            ],
            'Memory' => $config['memory_limit'] ?? 512 * 1024 * 1024, // 512MB default
            'CpuShares' => $config['cpu_shares'] ?? 1024,
            'Mounts' => [
                [
                    'Type' => 'volume',
                    'Source' => $volumeId,
                    'Target' => $config['volume']['mount_point'] ?? '/data',
                    'ReadOnly' => false
                ]
            ]
        ];
        
        // Port bindings
        if (isset($config['port_bindings'])) {
            $hostConfig['PortBindings'] = $config['port_bindings'];
        }
        
        // Resource limits
        if (isset($config['resources'])) {
            $hostConfig = array_merge($hostConfig, $config['resources']);
        }
        
        $containerConfig->setHostConfig($hostConfig);
        
        // Network configuration
        $containerConfig->setNetworkingConfig([
            'EndpointsConfig' => [
                $networkId => [
                    'IPAMConfig' => $config['network']['ip_config'] ?? null
                ]
            ]
        ]);
        
        // Create container
        $result = $this->docker->containers()->create($containerConfig, $config['name']);
        $containerId = $result['Id'];
        
        $this->logger->info('Container created', [
            'id' => $containerId,
            'name' => $config['name']
        ]);
        
        // Start container
        $this->docker->containers()->start($containerId);
        
        $this->logger->info('Container started', ['id' => $containerId]);
        
        return $containerId;
    }

    private function waitForHealthy(string $containerId, int $timeout): void
    {
        $this->logger->info('Waiting for container to be healthy', [
            'container_id' => $containerId,
            'timeout' => $timeout
        ]);
        
        $startTime = time();
        
        while (time() - $startTime < $timeout) {
            $container = $this->docker->containers()->inspect($containerId);
            
            $state = $container['State']['Status'];
            
            if ($state === 'exited') {
                throw new DockerException('Container exited unexpectedly');
            }
            
            if ($state === 'running') {
                // Check health if health check is configured
                if (isset($container['State']['Health'])) {
                    $health = $container['State']['Health']['Status'];
                    
                    if ($health === 'healthy') {
                        $this->logger->info('Container is healthy', ['container_id' => $containerId]);
                        return;
                    }
                    
                    if ($health === 'unhealthy') {
                        throw new DockerException('Container health check failed');
                    }
                    
                    // Still starting, continue waiting
                } else {
                    // No health check, assume healthy if running
                    $this->logger->info('Container is running (no health check)', ['container_id' => $containerId]);
                    return;
                }
            }
            
            sleep(2);
        }
        
        throw new DockerException('Timeout waiting for container to be healthy');
    }

    private function configureLoadBalancer(string $containerId, array $config): void
    {
        // This would integrate with your load balancer (nginx, haproxy, etc.)
        $this->logger->info('Configuring load balancer', [
            'container_id' => $containerId,
            'config' => $config
        ]);
        
        // Implementation depends on your load balancer
        // This is just a placeholder
    }

    private function cleanupFailedDeployment(array $config): void
    {
        $this->logger->info('Cleaning up failed deployment', ['name' => $config['name']]);
        
        try {
            // Try to remove container if it exists
            $this->docker->containers()->remove($config['name'], true, true);
        } catch (Exception $e) {
            $this->logger->debug('Container cleanup failed (may not exist)', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Scale a service by updating replica count
     */
    public function scaleService(string $serviceName, int $replicas): void
    {
        try {
            $this->logger->info('Scaling service', [
                'service' => $serviceName,
                'replicas' => $replicas
            ]);
            
            $service = $this->docker->services()->inspect($serviceName);
            $version = $service['Version']['Index'];
            
            $spec = $service['Spec'];
            $spec['Mode']['Replicated']['Replicas'] = $replicas;
            
            $this->docker->services()->update($serviceName, $version, $spec);
            
            $this->logger->info('Service scaled successfully', [
                'service' => $serviceName,
                'replicas' => $replicas
            ]);
            
        } catch (DockerException $e) {
            $this->logger->error('Service scaling failed', [
                'service' => $serviceName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Perform rolling update of a service
     */
    public function rollingUpdate(string $serviceName, string $newImage): void
    {
        try {
            $this->logger->info('Starting rolling update', [
                'service' => $serviceName,
                'new_image' => $newImage
            ]);
            
            // Pull new image first
            $this->pullImage($newImage);
            
            $service = $this->docker->services()->inspect($serviceName);
            $version = $service['Version']['Index'];
            
            $spec = $service['Spec'];
            $spec['TaskTemplate']['ContainerSpec']['Image'] = $newImage;
            
            // Configure update policy
            $spec['UpdateConfig'] = [
                'Parallelism' => 1,
                'Delay' => 10000000000, // 10 seconds in nanoseconds
                'FailureAction' => 'rollback',
                'Monitor' => 30000000000, // 30 seconds
                'MaxFailureRatio' => 0.1
            ];
            
            $this->docker->services()->update($serviceName, $version, $spec);
            
            $this->logger->info('Rolling update initiated', [
                'service' => $serviceName,
                'new_image' => $newImage
            ]);
            
        } catch (DockerException $e) {
            $this->logger->error('Rolling update failed', [
                'service' => $serviceName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Monitor system resources and containers
     */
    public function monitorSystem(): array
    {
        try {
            $info = $this->docker->system()->info();
            $containers = $this->docker->containers()->list(['all' => true]);
            
            $stats = [
                'system' => [
                    'containers_total' => $info['Containers'],
                    'containers_running' => $info['ContainersRunning'],
                    'containers_paused' => $info['ContainersPaused'],
                    'containers_stopped' => $info['ContainersStopped'],
                    'images' => $info['Images'],
                    'memory_total' => $info['MemTotal'],
                    'cpu_count' => $info['NCPU']
                ],
                'containers' => []
            ];
            
            foreach ($containers as $container) {
                if ($container['State'] === 'running') {
                    try {
                        $containerStats = $this->docker->containers()->stats($container['Id'], false, true);
                        
                        $stats['containers'][] = [
                            'id' => $container['Id'],
                            'name' => $container['Names'][0],
                            'image' => $container['Image'],
                            'memory_usage' => $containerStats['memory_stats']['usage'] ?? 0,
                            'memory_limit' => $containerStats['memory_stats']['limit'] ?? 0,
                            'cpu_usage' => $containerStats['cpu_stats']['cpu_usage']['total_usage'] ?? 0
                        ];
                    } catch (Exception $e) {
                        $this->logger->warning('Failed to get container stats', [
                            'container_id' => $container['Id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            return $stats;
            
        } catch (DockerException $e) {
            $this->logger->error('System monitoring failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Cleanup unused resources
     */
    public function cleanup(): array
    {
        $this->logger->info('Starting cleanup of unused resources');
        
        $results = [];
        
        try {
            // Prune stopped containers
            $containerPrune = $this->docker->containers()->prune();
            $results['containers'] = $containerPrune;
            $this->logger->info('Pruned containers', $containerPrune);
            
            // Prune unused images
            $imagePrune = $this->docker->images()->prune();
            $results['images'] = $imagePrune;
            $this->logger->info('Pruned images', $imagePrune);
            
            // Prune unused volumes
            $volumePrune = $this->docker->volumes()->prune();
            $results['volumes'] = $volumePrune;
            $this->logger->info('Pruned volumes', $volumePrune);
            
            // Prune unused networks
            $networkPrune = $this->docker->networks()->prune();
            $results['networks'] = $networkPrune;
            $this->logger->info('Pruned networks', $networkPrune);
            
            $totalSpace = ($results['containers']['SpaceReclaimed'] ?? 0) +
                         ($results['images']['SpaceReclaimed'] ?? 0) +
                         ($results['volumes']['SpaceReclaimed'] ?? 0);
            
            $this->logger->info('Cleanup completed', [
                'total_space_reclaimed' => $totalSpace
            ]);
            
            return $results;
            
        } catch (DockerException $e) {
            $this->logger->error('Cleanup failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}

// Example usage
try {
    $manager = new ProductionDockerManager([
        'docker_host' => 'unix:///var/run/docker.sock',
        'log_level' => Logger::INFO,
        'log_file' => 'logs/production-docker.log'
    ]);
    
    // Deploy a web application
    $deployment = $manager->deployWebApplication([
        'name' => 'web-app-prod',
        'image' => 'nginx:alpine',
        'version' => '1.2.3',
        'hostname' => 'web-app',
        'working_dir' => '/usr/share/nginx/html',
        'environment' => [
            'NGINX_HOST' => 'example.com',
            'NGINX_PORT' => '80'
        ],
        'ports' => ['80/tcp'],
        'port_bindings' => [
            '80/tcp' => [['HostPort' => '8080']]
        ],
        'network' => [
            'name' => 'production-network',
            'driver' => 'bridge'
        ],
        'volume' => [
            'name' => 'web-app-data',
            'mount_point' => '/usr/share/nginx/html'
        ],
        'resources' => [
            'Memory' => 256 * 1024 * 1024, // 256MB
            'CpuShares' => 512
        ],
        'health_check' => [
            'Test' => ['CMD', 'curl', '-f', 'http://localhost/'],
            'Interval' => 30000000000, // 30 seconds
            'Timeout' => 10000000000,  // 10 seconds
            'Retries' => 3
        ],
        'labels' => [
            'environment' => 'production',
            'service' => 'web-app'
        ]
    ]);
    
    echo "Deployment successful!\n";
    echo "Container ID: " . $deployment['container_id'] . "\n";
    echo "Access the application at: http://localhost:8080\n";
    
    // Monitor system
    $stats = $manager->monitorSystem();
    echo "System monitoring data collected\n";
    
    // Cleanup unused resources
    $cleanup = $manager->cleanup();
    echo "Cleanup completed, space reclaimed: " . 
         (($cleanup['containers']['SpaceReclaimed'] ?? 0) + 
          ($cleanup['images']['SpaceReclaimed'] ?? 0) + 
          ($cleanup['volumes']['SpaceReclaimed'] ?? 0)) . " bytes\n";
    
} catch (DockerException $e) {
    echo "Docker error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}