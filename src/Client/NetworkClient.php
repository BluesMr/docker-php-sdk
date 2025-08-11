<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Docker\API\Validator\ParameterValidator;
use Docker\API\Exception\InvalidParameterException;

/**
 * Network operations client
 * 
 * Provides methods for managing Docker networks including creation, inspection,
 * connection management, and maintenance operations.
 */
class NetworkClient extends BaseClient
{
    /**
     * List networks
     * 
     * Returns a list of networks. Networks can be filtered using the filters parameter.
     * 
     * @param array $filters Optional filters to apply to the network list
     *                      Supported filters:
     *                      - driver: string - Network driver name
     *                      - id: string - Network ID
     *                      - label: string|array - Label key or key=value pairs
     *                      - name: string - Network name
     *                      - scope: string - Network scope (local, global, swarm)
     *                      - type: string - Network type (custom, builtin)
     * 
     * @return array List of network objects, each containing:
     *               - Name: string - Network name
     *               - Id: string - Network ID
     *               - Created: string - Creation timestamp
     *               - Scope: string - Network scope
     *               - Driver: string - Network driver
     *               - EnableIPv6: bool - IPv6 enabled
     *               - IPAM: array - IP Address Management configuration
     *               - Internal: bool - Internal network flag
     *               - Attachable: bool - Attachable flag
     *               - Ingress: bool - Ingress network flag
     *               - ConfigFrom: array - Configuration source
     *               - ConfigOnly: bool - Configuration only flag
     *               - Containers: array - Connected containers
     *               - Options: array - Network options
     *               - Labels: array - Network labels
     * 
     * @throws InvalidParameterException If filters parameter is invalid
     * @throws DockerException If the API request fails
     * 
     * @example
     * ```php
     * // List all networks
     * $networks = $client->list();
     * 
     * // List networks with specific driver
     * $networks = $client->list(['driver' => 'bridge']);
     * 
     * // List networks with specific scope
     * $networks = $client->list(['scope' => 'local']);
     * ```
     */
    public function list(array $filters = []): array
    {
        // Validate filters parameter
        if (!empty($filters)) {
            ParameterValidator::validateArray($filters, 'filters');
            
            // Validate specific filter keys
            $validFilterKeys = ['driver', 'id', 'label', 'name', 'scope', 'type'];
            foreach (array_keys($filters) as $key) {
                if (!in_array($key, $validFilterKeys, true)) {
                    throw new InvalidParameterException("Invalid filter key '{$key}'. Valid keys are: " . implode(', ', $validFilterKeys));
                }
            }
            
            // Validate scope filter
            if (isset($filters['scope'])) {
                ParameterValidator::validateEnum($filters['scope'], ['local', 'global', 'swarm'], 'scope filter');
            }
            
            // Validate type filter
            if (isset($filters['type'])) {
                ParameterValidator::validateEnum($filters['type'], ['custom', 'builtin'], 'type filter');
            }
        }

        $query = $this->buildQuery([
            'filters' => empty($filters) ? null : json_encode($filters),
        ]);

        $response = $this->httpClient->get('/networks' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Inspect a network
     * 
     * Returns detailed information about a specific network.
     * 
     * @param string $id Network ID or name
     * @param bool $verbose Show detailed information including connected containers (default: false)
     * @param string $scope Network scope filter (default: 'local')
     *                     Valid values: 'local', 'global', 'swarm'
     * 
     * @return array Network object containing:
     *               - Name: string - Network name
     *               - Id: string - Network ID
     *               - Created: string - Creation timestamp
     *               - Scope: string - Network scope
     *               - Driver: string - Network driver
     *               - EnableIPv6: bool - IPv6 enabled
     *               - IPAM: array - IP Address Management configuration
     *               - Internal: bool - Internal network flag
     *               - Attachable: bool - Attachable flag
     *               - Ingress: bool - Ingress network flag
     *               - ConfigFrom: array - Configuration source
     *               - ConfigOnly: bool - Configuration only flag
     *               - Containers: array - Connected containers (if verbose=true)
     *               - Options: array - Network options
     *               - Labels: array - Network labels
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the network is not found or API request fails
     * 
     * @example
     * ```php
     * // Basic network inspection
     * $network = $client->inspect('bridge');
     * 
     * // Verbose inspection with container details
     * $network = $client->inspect('my-network', true);
     * ```
     */
    public function inspect(string $id, bool $verbose = false, string $scope = 'local'): array
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');
        ParameterValidator::validateEnum($scope, ['local', 'global', 'swarm'], 'scope');

        $query = $this->buildQuery([
            'verbose' => $verbose,
            'scope' => $scope,
        ]);

        $response = $this->httpClient->get("/networks/{$id}" . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Remove a network
     * 
     * Removes a network. The network cannot be removed if it has connected containers.
     * 
     * @param string $id Network ID or name
     * 
     * @return void
     * 
     * @throws InvalidParameterException If network ID is invalid
     * @throws DockerException If the network is not found, has connected containers, or API request fails
     * 
     * @example
     * ```php
     * $client->remove('my-network');
     * ```
     */
    public function remove(string $id): void
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');

        $this->httpClient->delete("/networks/{$id}");
    }

    /**
     * Create a network
     * 
     * Creates a new network with the specified configuration.
     * 
     * @param array $config Network configuration
     *                     Required fields:
     *                     - Name: string - Network name
     *                     Optional fields:
     *                     - CheckDuplicate: bool - Check for duplicate networks
     *                     - Driver: string - Network driver (default: "bridge")
     *                     - Internal: bool - Restrict external access
     *                     - Attachable: bool - Enable manual container attachment
     *                     - Ingress: bool - Create an ingress network
     *                     - IPAM: array - IP Address Management configuration
     *                     - EnableIPv6: bool - Enable IPv6 networking
     *                     - Options: array - Network driver options
     *                     - Labels: array - User-defined key/value metadata
     * 
     * @return array Network creation response containing:
     *               - Id: string - Network ID
     *               - Warning: string - Any warnings from the operation
     * 
     * @throws InvalidParameterException If configuration is invalid
     * @throws DockerException If the API request fails
     * 
     * @example
     * ```php
     * // Create a simple bridge network
     * $network = $client->create([
     *     'Name' => 'my-network'
     * ]);
     * 
     * // Create a custom network with IPAM configuration
     * $network = $client->create([
     *     'Name' => 'custom-network',
     *     'Driver' => 'bridge',
     *     'IPAM' => [
     *         'Config' => [
     *             [
     *                 'Subnet' => '172.20.0.0/16',
     *                 'Gateway' => '172.20.0.1'
     *             ]
     *         ]
     *     ],
     *     'Labels' => [
     *         'environment' => 'production'
     *     ]
     * ]);
     * ```
     */
    public function create(array $config): array
    {
        ParameterValidator::validateArray($config, 'config');

        // Validate required Name field
        if (!isset($config['Name'])) {
            throw new InvalidParameterException("Network name is required");
        }
        
        ParameterValidator::validateString($config['Name'], 'Name');
        
        // Validate network name format
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $config['Name'])) {
            throw new InvalidParameterException("Network name must start with alphanumeric character and contain only alphanumeric, underscore, period, or hyphen characters");
        }

        // Validate Driver if provided
        if (isset($config['Driver'])) {
            ParameterValidator::validateString($config['Driver'], 'Driver');
        }

        // Validate boolean fields
        $booleanFields = ['CheckDuplicate', 'Internal', 'Attachable', 'Ingress', 'EnableIPv6'];
        foreach ($booleanFields as $field) {
            if (isset($config[$field]) && !is_bool($config[$field])) {
                throw new InvalidParameterException("Field '{$field}' must be a boolean value");
            }
        }

        // Validate IPAM configuration if provided
        if (isset($config['IPAM'])) {
            ParameterValidator::validateArray($config['IPAM'], 'IPAM');
            
            if (isset($config['IPAM']['Config'])) {
                ParameterValidator::validateArray($config['IPAM']['Config'], 'IPAM.Config');
                
                foreach ($config['IPAM']['Config'] as $index => $ipamConfig) {
                    if (!is_array($ipamConfig)) {
                        throw new InvalidParameterException("IPAM.Config[{$index}] must be an array");
                    }
                    
                    // Validate subnet format if provided
                    if (isset($ipamConfig['Subnet'])) {
                        if (!filter_var($ipamConfig['Subnet'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) &&
                            !preg_match('/^(?:\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $ipamConfig['Subnet']) &&
                            !preg_match('/^[0-9a-fA-F:]+\/\d{1,3}$/', $ipamConfig['Subnet'])) {
                            throw new InvalidParameterException("Invalid subnet format in IPAM.Config[{$index}].Subnet");
                        }
                    }
                }
            }
        }

        // Validate Options if provided
        if (isset($config['Options'])) {
            ParameterValidator::validateArray($config['Options'], 'Options');
            
            foreach ($config['Options'] as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    throw new InvalidParameterException("Network options must be string key-value pairs");
                }
            }
        }

        // Validate Labels if provided
        if (isset($config['Labels'])) {
            ParameterValidator::validateArray($config['Labels'], 'Labels');
            
            foreach ($config['Labels'] as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    throw new InvalidParameterException("Labels must be string key-value pairs");
                }
            }
        }

        $response = $this->httpClient->post('/networks/create', $this->jsonBody($config));
        return $this->getJsonResponse($response);
    }

    /**
     * Connect a container to a network
     * 
     * Connects a container to a network with optional configuration.
     * 
     * @param string $id Network ID or name
     * @param array $config Connection configuration
     *                     Required fields:
     *                     - Container: string - Container ID or name
     *                     Optional fields:
     *                     - EndpointConfig: array - Endpoint configuration
     *                       - IPAMConfig: array - IPAM configuration
     *                         - IPv4Address: string - Static IPv4 address
     *                         - IPv6Address: string - Static IPv6 address
     *                       - Links: array - Container links
     *                       - Aliases: array - Network aliases
     *                       - NetworkID: string - Network ID
     *                       - EndpointID: string - Endpoint ID
     *                       - Gateway: string - Gateway address
     *                       - IPAddress: string - IP address
     *                       - IPPrefixLen: int - IP prefix length
     *                       - IPv6Gateway: string - IPv6 gateway
     *                       - GlobalIPv6Address: string - Global IPv6 address
     *                       - GlobalIPv6PrefixLen: int - Global IPv6 prefix length
     *                       - MacAddress: string - MAC address
     *                       - DriverOpts: array - Driver options
     * 
     * @return void
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the network or container is not found, or API request fails
     * 
     * @example
     * ```php
     * // Simple connection
     * $client->connect('my-network', [
     *     'Container' => 'my-container'
     * ]);
     * 
     * // Connection with static IP
     * $client->connect('my-network', [
     *     'Container' => 'my-container',
     *     'EndpointConfig' => [
     *         'IPAMConfig' => [
     *             'IPv4Address' => '172.20.0.10'
     *         ],
     *         'Aliases' => ['web-server']
     *     ]
     * ]);
     * ```
     */
    public function connect(string $id, array $config): void
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');
        ParameterValidator::validateArray($config, 'config');

        // Validate required Container field
        if (!isset($config['Container'])) {
            throw new InvalidParameterException("Container ID or name is required");
        }
        
        ParameterValidator::validateString($config['Container'], 'Container');
        ParameterValidator::validateId($config['Container'], 'Container');

        // Validate EndpointConfig if provided
        if (isset($config['EndpointConfig'])) {
            ParameterValidator::validateArray($config['EndpointConfig'], 'EndpointConfig');
            
            $endpointConfig = $config['EndpointConfig'];
            
            // Validate IPAMConfig if provided
            if (isset($endpointConfig['IPAMConfig'])) {
                ParameterValidator::validateArray($endpointConfig['IPAMConfig'], 'EndpointConfig.IPAMConfig');
                
                $ipamConfig = $endpointConfig['IPAMConfig'];
                
                // Validate IP addresses
                if (isset($ipamConfig['IPv4Address']) && !filter_var($ipamConfig['IPv4Address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    throw new InvalidParameterException("Invalid IPv4 address format");
                }
                
                if (isset($ipamConfig['IPv6Address']) && !filter_var($ipamConfig['IPv6Address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    throw new InvalidParameterException("Invalid IPv6 address format");
                }
            }
            
            // Validate Aliases if provided
            if (isset($endpointConfig['Aliases'])) {
                ParameterValidator::validateArray($endpointConfig['Aliases'], 'EndpointConfig.Aliases');
                
                foreach ($endpointConfig['Aliases'] as $alias) {
                    if (!is_string($alias) || empty($alias)) {
                        throw new InvalidParameterException("Network aliases must be non-empty strings");
                    }
                }
            }
        }

        $this->httpClient->post("/networks/{$id}/connect", $this->jsonBody($config));
    }

    /**
     * Disconnect a container from a network
     * 
     * Disconnects a container from a network.
     * 
     * @param string $id Network ID or name
     * @param array $config Disconnection configuration
     *                     Required fields:
     *                     - Container: string - Container ID or name
     *                     Optional fields:
     *                     - Force: bool - Force disconnection (default: false)
     * 
     * @return void
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the network or container is not found, or API request fails
     * 
     * @example
     * ```php
     * // Simple disconnection
     * $client->disconnect('my-network', [
     *     'Container' => 'my-container'
     * ]);
     * 
     * // Force disconnection
     * $client->disconnect('my-network', [
     *     'Container' => 'my-container',
     *     'Force' => true
     * ]);
     * ```
     */
    public function disconnect(string $id, array $config): void
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');
        ParameterValidator::validateArray($config, 'config');

        // Validate required Container field
        if (!isset($config['Container'])) {
            throw new InvalidParameterException("Container ID or name is required");
        }
        
        ParameterValidator::validateString($config['Container'], 'Container');
        ParameterValidator::validateId($config['Container'], 'Container');

        // Validate Force field if provided
        if (isset($config['Force']) && !is_bool($config['Force'])) {
            throw new InvalidParameterException("Force field must be a boolean value");
        }

        $this->httpClient->post("/networks/{$id}/disconnect", $this->jsonBody($config));
    }

    /**
     * Delete unused networks
     * 
     * Removes all networks that are not used by at least one container.
     * 
     * @param array $filters Optional filters to apply when pruning networks
     *                      Supported filters:
     *                      - until: string - Prune networks created before this timestamp
     *                      - label: string|array - Only prune networks with specified labels
     * 
     * @return array Prune response containing:
     *               - NetworksDeleted: array - List of deleted network names
     * 
     * @throws InvalidParameterException If filters parameter is invalid
     * @throws DockerException If the API request fails
     * 
     * @example
     * ```php
     * // Prune all unused networks
     * $result = $client->prune();
     * echo "Deleted networks: " . implode(', ', $result['NetworksDeleted']);
     * 
     * // Prune networks with specific label
     * $result = $client->prune(['label' => 'environment=development']);
     * ```
     */
    public function prune(array $filters = []): array
    {
        // Validate filters parameter
        if (!empty($filters)) {
            ParameterValidator::validateArray($filters, 'filters');
            
            // Validate specific filter keys
            $validFilterKeys = ['until', 'label'];
            foreach (array_keys($filters) as $key) {
                if (!in_array($key, $validFilterKeys, true)) {
                    throw new InvalidParameterException("Invalid filter key '{$key}'. Valid keys are: " . implode(', ', $validFilterKeys));
                }
            }
        }

        $query = $this->buildQuery([
            'filters' => empty($filters) ? null : json_encode($filters),
        ]);

        $response = $this->httpClient->post('/networks/prune' . $query);
        return $this->getJsonResponse($response);
    }
}