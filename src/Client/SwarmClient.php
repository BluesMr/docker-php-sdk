<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Docker\API\Validator\ParameterValidator;
use Docker\API\Exception\InvalidParameterException;

/**
 * Swarm operations client
 * 
 * Provides methods for managing Docker Swarm clusters including initialization,
 * joining, leaving, and configuration management.
 */
class SwarmClient extends BaseClient
{
    /**
     * Inspect swarm
     * 
     * Returns information about the swarm cluster.
     * 
     * @return array Swarm information containing:
     *               - ID: string - Swarm ID
     *               - Version: array - Object version information
     *               - CreatedAt: string - Creation timestamp
     *               - UpdatedAt: string - Last update timestamp
     *               - Spec: array - Swarm specification
     *                 - Name: string - Swarm name
     *                 - Labels: array - Swarm labels
     *                 - Orchestration: array - Orchestration settings
     *                 - Raft: array - Raft consensus settings
     *                 - Dispatcher: array - Dispatcher settings
     *                 - CAConfig: array - Certificate authority configuration
     *                 - EncryptionConfig: array - Encryption configuration
     *                 - TaskDefaults: array - Default task settings
     *               - TLSInfo: array - TLS information
     *               - RootRotationInProgress: bool - Root rotation status
     *               - DataPathPort: int - Data path port
     *               - DefaultAddrPool: array - Default address pool
     *               - SubnetSize: int - Subnet size
     *               - JoinTokens: array - Join tokens
     *                 - Worker: string - Worker join token
     *                 - Manager: string - Manager join token
     * 
     * @throws DockerException If not in swarm mode or API request fails
     * 
     * @example
     * ```php
     * $swarm = $client->inspect();
     * echo "Swarm ID: " . $swarm['ID'];
     * echo "Swarm name: " . $swarm['Spec']['Name'];
     * ```
     */
    public function inspect(): array
    {
        $response = $this->httpClient->get('/swarm');
        return $this->getJsonResponse($response);
    }

    /**
     * Initialize a new swarm
     * 
     * Initializes a new swarm cluster with this node as the first manager.
     * 
     * @param array $config Swarm initialization configuration
     *                     Optional fields:
     *                     - ListenAddr: string - Listen address (default: "0.0.0.0:2377")
     *                     - AdvertiseAddr: string - Advertise address
     *                     - DataPathAddr: string - Data path address
     *                     - DataPathPort: int - Data path port
     *                     - DefaultAddrPool: array - Default address pool
     *                     - ForceNewCluster: bool - Force new cluster
     *                     - SubnetSize: int - Subnet size
     *                     - Spec: array - Swarm specification
     *                       - Name: string - Swarm name
     *                       - Labels: array - Swarm labels
     *                       - Orchestration: array - Orchestration settings
     *                       - Raft: array - Raft consensus settings
     *                       - Dispatcher: array - Dispatcher settings
     *                       - CAConfig: array - Certificate authority configuration
     *                       - EncryptionConfig: array - Encryption configuration
     *                       - TaskDefaults: array - Default task settings
     * 
     * @return string Node ID of the initialized swarm manager
     * 
     * @throws InvalidParameterException If configuration is invalid
     * @throws DockerException If already in swarm mode or API request fails
     * 
     * @example
     * ```php
     * // Initialize swarm with default settings
     * $nodeId = $client->init([]);
     * 
     * // Initialize swarm with custom configuration
     * $nodeId = $client->init([
     *     'ListenAddr' => '0.0.0.0:2377',
     *     'AdvertiseAddr' => '192.168.1.100:2377',
     *     'Spec' => [
     *         'Name' => 'my-swarm',
     *         'Labels' => [
     *             'environment' => 'production'
     *         ],
     *         'Orchestration' => [
     *             'TaskHistoryRetentionLimit' => 10
     *         ]
     *     ]
     * ]);
     * ```
     */
    public function init(array $config): string
    {
        ParameterValidator::validateArray($config, 'config');

        // Validate string fields
        $stringFields = ['ListenAddr', 'AdvertiseAddr', 'DataPathAddr'];
        foreach ($stringFields as $field) {
            if (isset($config[$field])) {
                if (!is_string($config[$field]) || empty($config[$field])) {
                    throw new InvalidParameterException("Field '{$field}' must be a non-empty string");
                }
            }
        }

        // Validate integer fields
        if (isset($config['DataPathPort'])) {
            ParameterValidator::validateInteger($config['DataPathPort'], 'DataPathPort', 1, 65535);
        }

        if (isset($config['SubnetSize'])) {
            ParameterValidator::validateInteger($config['SubnetSize'], 'SubnetSize', 1, 32);
        }

        // Validate boolean fields
        if (isset($config['ForceNewCluster']) && !is_bool($config['ForceNewCluster'])) {
            throw new InvalidParameterException("Field 'ForceNewCluster' must be a boolean value");
        }

        // Validate DefaultAddrPool if provided
        if (isset($config['DefaultAddrPool'])) {
            ParameterValidator::validateArray($config['DefaultAddrPool'], 'DefaultAddrPool');
            
            foreach ($config['DefaultAddrPool'] as $index => $pool) {
                if (!is_string($pool)) {
                    throw new InvalidParameterException("DefaultAddrPool[{$index}] must be a string");
                }
                
                if (!preg_match('/^(?:\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $pool)) {
                    throw new InvalidParameterException("DefaultAddrPool[{$index}] must be a valid CIDR notation");
                }
            }
        }

        // Validate Spec if provided
        if (isset($config['Spec'])) {
            $this->validateSwarmSpec($config['Spec']);
        }

        $response = $this->httpClient->post('/swarm/init', $this->jsonBody($config));
        return $this->getStringResponse($response);
    }

    /**
     * Join an existing swarm
     * 
     * Joins this node to an existing swarm cluster.
     * 
     * @param array $config Join configuration
     *                     Required fields:
     *                     - ListenAddr: string - Listen address
     *                     - AdvertiseAddr: string - Advertise address
     *                     - RemoteAddrs: array - Remote manager addresses
     *                     - JoinToken: string - Join token
     *                     Optional fields:
     *                     - DataPathAddr: string - Data path address
     * 
     * @return void
     * 
     * @throws InvalidParameterException If configuration is invalid
     * @throws DockerException If already in swarm mode or API request fails
     * 
     * @example
     * ```php
     * $client->join([
     *     'ListenAddr' => '0.0.0.0:2377',
     *     'AdvertiseAddr' => '192.168.1.101:2377',
     *     'RemoteAddrs' => ['192.168.1.100:2377'],
     *     'JoinToken' => 'SWMTKN-1-...'
     * ]);
     * ```
     */
    public function join(array $config): void
    {
        ParameterValidator::validateArray($config, 'config');

        // Validate required fields
        $requiredFields = ['ListenAddr', 'AdvertiseAddr', 'RemoteAddrs', 'JoinToken'];
        foreach ($requiredFields as $field) {
            if (!isset($config[$field])) {
                throw new InvalidParameterException("Field '{$field}' is required");
            }
        }

        // Validate string fields
        $stringFields = ['ListenAddr', 'AdvertiseAddr', 'JoinToken', 'DataPathAddr'];
        foreach ($stringFields as $field) {
            if (isset($config[$field])) {
                if (!is_string($config[$field]) || empty($config[$field])) {
                    throw new InvalidParameterException("Field '{$field}' must be a non-empty string");
                }
            }
        }

        // Validate RemoteAddrs
        ParameterValidator::validateArray($config['RemoteAddrs'], 'RemoteAddrs', false);
        
        foreach ($config['RemoteAddrs'] as $index => $addr) {
            if (!is_string($addr) || empty($addr)) {
                throw new InvalidParameterException("RemoteAddrs[{$index}] must be a non-empty string");
            }
        }

        // Validate JoinToken format
        if (!preg_match('/^SWMTKN-1-[a-zA-Z0-9-]+$/', $config['JoinToken'])) {
            throw new InvalidParameterException("Invalid join token format");
        }

        $this->httpClient->post('/swarm/join', $this->jsonBody($config));
    }

    /**
     * Leave a swarm
     * 
     * Leaves the swarm cluster.
     * 
     * @param bool $force Force leave even if this is the last manager (default: false)
     * 
     * @return void
     * 
     * @throws DockerException If not in swarm mode or API request fails
     * 
     * @example
     * ```php
     * // Leave swarm gracefully
     * $client->leave();
     * 
     * // Force leave (dangerous for last manager)
     * $client->leave(true);
     * ```
     */
    public function leave(bool $force = false): void
    {
        $query = $this->buildQuery(['force' => $force]);
        $this->httpClient->post('/swarm/leave' . $query);
    }

    /**
     * Update a swarm
     * 
     * Updates the swarm configuration.
     * 
     * @param int $version Current version of the swarm object being updated
     * @param array $config Updated swarm specification
     * @param bool $rotateWorkerToken Rotate worker join token (default: false)
     * @param bool $rotateManagerToken Rotate manager join token (default: false)
     * @param bool $rotateManagerUnlockKey Rotate manager unlock key (default: false)
     * 
     * @return void
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If not in swarm mode, version mismatch, or API request fails
     * 
     * @example
     * ```php
     * // Update swarm name
     * $swarm = $client->inspect();
     * $version = $swarm['Version']['Index'];
     * 
     * $client->update($version, [
     *     'Name' => 'updated-swarm-name',
     *     'Labels' => [
     *         'environment' => 'production',
     *         'region' => 'us-west'
     *     ]
     * ]);
     * 
     * // Rotate tokens
     * $client->update($version, [], true, true);
     * ```
     */
    public function update(int $version, array $config, bool $rotateWorkerToken = false, bool $rotateManagerToken = false, bool $rotateManagerUnlockKey = false): void
    {
        ParameterValidator::validateInteger($version, 'version', 0);
        ParameterValidator::validateArray($config, 'config');

        // Validate swarm spec if provided
        if (!empty($config)) {
            $this->validateSwarmSpec($config);
        }

        $query = $this->buildQuery([
            'version' => $version,
            'rotateWorkerToken' => $rotateWorkerToken,
            'rotateManagerToken' => $rotateManagerToken,
            'rotateManagerUnlockKey' => $rotateManagerUnlockKey,
        ]);

        $this->httpClient->post('/swarm/update' . $query, $this->jsonBody($config));
    }

    /**
     * Get the unlock key
     * 
     * Returns the unlock key for locked swarm managers.
     * 
     * @return array Unlock key response containing:
     *               - UnlockKey: string - The unlock key
     * 
     * @throws DockerException If not in swarm mode, swarm not locked, or API request fails
     * 
     * @example
     * ```php
     * $result = $client->unlockkey();
     * echo "Unlock key: " . $result['UnlockKey'];
     * ```
     */
    public function unlockkey(): array
    {
        $response = $this->httpClient->get('/swarm/unlockkey');
        return $this->getJsonResponse($response);
    }

    /**
     * Unlock a locked manager
     * 
     * Unlocks a locked swarm manager using the unlock key.
     * 
     * @param array $config Unlock configuration
     *                     Required fields:
     *                     - UnlockKey: string - The unlock key
     * 
     * @return void
     * 
     * @throws InvalidParameterException If configuration is invalid
     * @throws DockerException If not in swarm mode, invalid key, or API request fails
     * 
     * @example
     * ```php
     * $client->unlock([
     *     'UnlockKey' => 'SWMKEY-1-...'
     * ]);
     * ```
     */
    public function unlock(array $config): void
    {
        ParameterValidator::validateArray($config, 'config');

        // Validate required UnlockKey field
        if (!isset($config['UnlockKey'])) {
            throw new InvalidParameterException("UnlockKey is required");
        }

        if (!is_string($config['UnlockKey']) || empty($config['UnlockKey'])) {
            throw new InvalidParameterException("UnlockKey must be a non-empty string");
        }

        // Validate unlock key format
        if (!preg_match('/^SWMKEY-1-[a-zA-Z0-9]+$/', $config['UnlockKey'])) {
            throw new InvalidParameterException("Invalid unlock key format");
        }

        $this->httpClient->post('/swarm/unlock', $this->jsonBody($config));
    }

    /**
     * Validate swarm specification
     * 
     * @param array $spec Swarm specification to validate
     * @throws InvalidParameterException If specification is invalid
     */
    private function validateSwarmSpec(array $spec): void
    {
        ParameterValidator::validateArray($spec, 'Spec');

        // Validate Name if provided
        if (isset($spec['Name'])) {
            if (!is_string($spec['Name']) || empty($spec['Name'])) {
                throw new InvalidParameterException("Swarm name must be a non-empty string");
            }
        }

        // Validate Labels if provided
        if (isset($spec['Labels'])) {
            ParameterValidator::validateArray($spec['Labels'], 'Labels');
            
            foreach ($spec['Labels'] as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    throw new InvalidParameterException("Labels must be string key-value pairs");
                }
            }
        }

        // Validate Orchestration if provided
        if (isset($spec['Orchestration'])) {
            ParameterValidator::validateArray($spec['Orchestration'], 'Orchestration');
            
            if (isset($spec['Orchestration']['TaskHistoryRetentionLimit'])) {
                ParameterValidator::validateInteger($spec['Orchestration']['TaskHistoryRetentionLimit'], 'TaskHistoryRetentionLimit', 0);
            }
        }

        // Validate Raft if provided
        if (isset($spec['Raft'])) {
            ParameterValidator::validateArray($spec['Raft'], 'Raft');
            
            $raftIntFields = ['SnapshotInterval', 'KeepOldSnapshots', 'LogEntriesForSlowFollowers', 'ElectionTick', 'HeartbeatTick'];
            foreach ($raftIntFields as $field) {
                if (isset($spec['Raft'][$field])) {
                    ParameterValidator::validateInteger($spec['Raft'][$field], "Raft.{$field}", 1);
                }
            }
        }

        // Validate Dispatcher if provided
        if (isset($spec['Dispatcher'])) {
            ParameterValidator::validateArray($spec['Dispatcher'], 'Dispatcher');
            
            if (isset($spec['Dispatcher']['HeartbeatPeriod'])) {
                ParameterValidator::validateInteger($spec['Dispatcher']['HeartbeatPeriod'], 'Dispatcher.HeartbeatPeriod', 1);
            }
        }
    }
}