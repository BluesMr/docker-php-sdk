<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Docker\API\Validator\ParameterValidator;
use Docker\API\Exception\InvalidParameterException;

/**
 * Volume operations client
 * 
 * Provides methods for managing Docker volumes including creation, inspection,
 * removal, and maintenance operations.
 */
class VolumeClient extends BaseClient
{
    /**
     * List volumes
     * 
     * Returns a list of volumes. Volumes can be filtered using the filters parameter.
     * 
     * @param array $filters Optional filters to apply to the volume list
     *                      Supported filters:
     *                      - dangling: boolean - Show only dangling volumes
     *                      - driver: string - Volume driver name
     *                      - label: string|array - Label key or key=value pairs
     *                      - name: string - Volume name
     * 
     * @return array Array containing:
     *               - Volumes: array - List of volume objects
     *               - Warnings: array - Any warnings from the operation
     * 
     * @throws InvalidParameterException If filters parameter is invalid
     * @throws DockerException If the API request fails
     * 
     * @example
     * ```php
     * // List all volumes
     * $volumes = $client->list();
     * 
     * // List volumes with specific driver
     * $volumes = $client->list(['driver' => 'local']);
     * 
     * // List dangling volumes
     * $volumes = $client->list(['dangling' => true]);
     * ```
     */
    public function list(array $filters = []): array
    {
        // Validate filters parameter
        if (!empty($filters)) {
            ParameterValidator::validateArray($filters, 'filters');
            
            // Validate specific filter keys
            $validFilterKeys = ['dangling', 'driver', 'label', 'name'];
            foreach (array_keys($filters) as $key) {
                if (!in_array($key, $validFilterKeys, true)) {
                    throw new InvalidParameterException("Invalid filter key '{$key}'. Valid keys are: " . implode(', ', $validFilterKeys));
                }
            }
            
            // Validate dangling filter
            if (isset($filters['dangling']) && !is_bool($filters['dangling'])) {
                throw new InvalidParameterException("Filter 'dangling' must be a boolean value");
            }
        }

        $query = $this->buildQuery([
            'filters' => empty($filters) ? null : json_encode($filters),
        ]);

        $response = $this->httpClient->get('/volumes' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Create a volume
     * 
     * Creates a new volume with the specified configuration.
     * 
     * @param array $config Volume configuration
     *                     Required fields: None (all fields are optional)
     *                     Optional fields:
     *                     - Name: string - Volume name (auto-generated if not provided)
     *                     - Driver: string - Volume driver name (default: "local")
     *                     - DriverOpts: array - Driver-specific options
     *                     - Labels: array - User-defined key/value metadata
     * 
     * @return array Volume object containing:
     *               - Name: string - Volume name
     *               - Driver: string - Volume driver
     *               - Mountpoint: string - Volume mount point on host
     *               - CreatedAt: string - Creation timestamp
     *               - Status: array - Driver-specific status information
     *               - Labels: array - Volume labels
     *               - Scope: string - Volume scope
     *               - Options: array - Volume options
     * 
     * @throws InvalidParameterException If configuration is invalid
     * @throws DockerException If the API request fails
     * 
     * @example
     * ```php
     * // Create a simple local volume
     * $volume = $client->create([
     *     'Name' => 'my-volume'
     * ]);
     * 
     * // Create volume with custom driver and options
     * $volume = $client->create([
     *     'Name' => 'nfs-volume',
     *     'Driver' => 'nfs',
     *     'DriverOpts' => [
     *         'device' => 'nfs-server:/path/to/dir',
     *         'o' => 'addr=nfs-server,rw'
     *     ],
     *     'Labels' => [
     *         'environment' => 'production',
     *         'backup' => 'daily'
     *     ]
     * ]);
     * ```
     */
    public function create(array $config): array
    {
        ParameterValidator::validateArray($config, 'config');

        // Validate Name if provided
        if (isset($config['Name'])) {
            if (!is_string($config['Name']) || empty($config['Name'])) {
                throw new InvalidParameterException("Volume name must be a non-empty string");
            }
            
            // Validate volume name format
            if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $config['Name'])) {
                throw new InvalidParameterException("Volume name must start with alphanumeric character and contain only alphanumeric, underscore, period, or hyphen characters");
            }
        }

        // Validate Driver if provided
        if (isset($config['Driver'])) {
            if (!is_string($config['Driver']) || empty($config['Driver'])) {
                throw new InvalidParameterException("Volume driver must be a non-empty string");
            }
        }

        // Validate DriverOpts if provided
        if (isset($config['DriverOpts'])) {
            ParameterValidator::validateArray($config['DriverOpts'], 'DriverOpts');
            
            foreach ($config['DriverOpts'] as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    throw new InvalidParameterException("Driver options must be string key-value pairs");
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

        $response = $this->httpClient->post('/volumes/create', $this->jsonBody($config));
        return $this->getJsonResponse($response);
    }

    /**
     * Inspect a volume
     * 
     * Returns detailed information about a specific volume.
     * 
     * @param string $name Volume name or ID
     * 
     * @return array Volume object containing:
     *               - Name: string - Volume name
     *               - Driver: string - Volume driver
     *               - Mountpoint: string - Volume mount point on host
     *               - CreatedAt: string - Creation timestamp
     *               - Status: array - Driver-specific status information
     *               - Labels: array - Volume labels
     *               - Scope: string - Volume scope
     *               - Options: array - Volume options
     *               - UsageData: array - Volume usage data (if available)
     * 
     * @throws InvalidParameterException If volume name is invalid
     * @throws DockerException If the volume is not found or API request fails
     * 
     * @example
     * ```php
     * $volume = $client->inspect('my-volume');
     * echo "Volume driver: " . $volume['Driver'];
     * echo "Mount point: " . $volume['Mountpoint'];
     * ```
     */
    public function inspect(string $name): array
    {
        ParameterValidator::validateString($name, 'name');
        ParameterValidator::validateId($name, 'name');

        $response = $this->httpClient->get("/volumes/{$name}");
        return $this->getJsonResponse($response);
    }

    /**
     * Remove a volume
     * 
     * Removes a volume. The volume cannot be removed if it is in use by a container.
     * 
     * @param string $name Volume name or ID
     * @param bool $force Force removal of the volume (default: false)
     *                   When true, removes the volume even if it's in use
     * 
     * @return void
     * 
     * @throws InvalidParameterException If volume name is invalid
     * @throws DockerException If the volume is not found, in use (when force=false), or API request fails
     * 
     * @example
     * ```php
     * // Remove volume (fails if in use)
     * $client->remove('my-volume');
     * 
     * // Force remove volume
     * $client->remove('my-volume', true);
     * ```
     */
    public function remove(string $name, bool $force = false): void
    {
        ParameterValidator::validateString($name, 'name');
        ParameterValidator::validateId($name, 'name');

        $query = $this->buildQuery(['force' => $force]);
        $this->httpClient->delete("/volumes/{$name}" . $query);
    }

    /**
     * Delete unused volumes
     * 
     * Removes all volumes that are not used by at least one container.
     * 
     * @param array $filters Optional filters to apply when pruning volumes
     *                      Supported filters:
     *                      - label: string|array - Only prune volumes with specified labels
     *                      - all: boolean - Remove all unused volumes, not just anonymous ones
     * 
     * @return array Prune response containing:
     *               - VolumesDeleted: array - List of deleted volume names
     *               - SpaceReclaimed: int - Amount of disk space reclaimed in bytes
     * 
     * @throws InvalidParameterException If filters parameter is invalid
     * @throws DockerException If the API request fails
     * 
     * @example
     * ```php
     * // Prune all unused volumes
     * $result = $client->prune();
     * echo "Deleted volumes: " . implode(', ', $result['VolumesDeleted']);
     * echo "Space reclaimed: " . $result['SpaceReclaimed'] . " bytes";
     * 
     * // Prune volumes with specific label
     * $result = $client->prune(['label' => 'environment=development']);
     * ```
     */
    public function prune(array $filters = []): array
    {
        // Validate filters parameter
        if (!empty($filters)) {
            ParameterValidator::validateArray($filters, 'filters');
            
            // Validate specific filter keys
            $validFilterKeys = ['label', 'all'];
            foreach (array_keys($filters) as $key) {
                if (!in_array($key, $validFilterKeys, true)) {
                    throw new InvalidParameterException("Invalid filter key '{$key}'. Valid keys are: " . implode(', ', $validFilterKeys));
                }
            }
            
            // Validate all filter
            if (isset($filters['all']) && !is_bool($filters['all'])) {
                throw new InvalidParameterException("Filter 'all' must be a boolean value");
            }
        }

        $query = $this->buildQuery([
            'filters' => empty($filters) ? null : json_encode($filters),
        ]);

        $response = $this->httpClient->post('/volumes/prune' . $query);
        return $this->getJsonResponse($response);
    }
}