<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Docker\API\Validator\ParameterValidator;
use Docker\API\Exception\InvalidParameterException;
use Psr\Http\Message\StreamInterface;

/**
 * Service operations client
 * 
 * Provides methods for managing Docker Swarm services including creation,
 * inspection, updating, and log retrieval operations.
 */
class ServiceClient extends BaseClient
{
    /**
     * List services
     * 
     * Returns a list of services in the swarm. Services can be filtered using the filters parameter.
     * 
     * @param array $filters Optional filters to apply to the service list
     *                      Supported filters:
     *                      - id: string - Service ID
     *                      - label: string|array - Label key or key=value pairs
     *                      - mode: string - Service mode (replicated, global)
     *                      - name: string - Service name
     * @param bool $status Include service status with count of running and desired tasks (default: false)
     * 
     * @return array List of service objects, each containing:
     *               - ID: string - Service ID
     *               - Version: array - Object version information
     *               - CreatedAt: string - Creation timestamp
     *               - UpdatedAt: string - Last update timestamp
     *               - Spec: array - Service specification
     *               - Endpoint: array - Service endpoint information
     *               - UpdateStatus: array - Update status (if applicable)
     *               - ServiceStatus: array - Service status (if status=true)
     * 
     * @throws InvalidParameterException If filters parameter is invalid
     * @throws DockerException If not in swarm mode or API request fails
     * 
     * @example
     * ```php
     * // List all services
     * $services = $client->list();
     * 
     * // List services with status information
     * $services = $client->list([], true);
     * 
     * // List services with specific label
     * $services = $client->list(['label' => 'environment=production']);
     * 
     * // List replicated services only
     * $services = $client->list(['mode' => 'replicated']);
     * ```
     */
    public function list(array $filters = [], bool $status = false): array
    {
        // Validate filters parameter
        if (!empty($filters)) {
            ParameterValidator::validateArray($filters, 'filters');
            
            // Validate specific filter keys
            $validFilterKeys = ['id', 'label', 'mode', 'name'];
            foreach (array_keys($filters) as $key) {
                if (!in_array($key, $validFilterKeys, true)) {
                    throw new InvalidParameterException("Invalid filter key '{$key}'. Valid keys are: " . implode(', ', $validFilterKeys));
                }
            }
            
            // Validate mode filter
            if (isset($filters['mode'])) {
                ParameterValidator::validateEnum($filters['mode'], ['replicated', 'global'], 'mode filter');
            }
        }

        $query = $this->buildQuery([
            'filters' => empty($filters) ? null : json_encode($filters),
            'status' => $status,
        ]);
        
        $response = $this->httpClient->get('/services' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Create a service
     * 
     * Creates a new service with the specified configuration.
     * 
     * @param array $config Service configuration (ServiceSpec)
     *                     Required fields:
     *                     - Name: string - Service name
     *                     - TaskTemplate: array - Task template specification
     *                     Optional fields:
     *                     - Labels: array - Service labels
     *                     - Mode: array - Service mode (Replicated or Global)
     *                     - UpdateConfig: array - Update configuration
     *                     - RollbackConfig: array - Rollback configuration
     *                     - Networks: array - Network attachments
     *                     - EndpointSpec: array - Endpoint specification
     * @param string|null $registryAuth Registry authentication (base64 encoded JSON)
     * 
     * @return array Service creation response containing:
     *               - ID: string - Service ID
     *               - Warning: string - Any warnings from the operation
     * 
     * @throws InvalidParameterException If configuration is invalid
     * @throws DockerException If not in swarm mode or API request fails
     * 
     * @example
     * ```php
     * // Create a simple replicated service
     * $service = $client->create([
     *     'Name' => 'web-server',
     *     'TaskTemplate' => [
     *         'ContainerSpec' => [
     *             'Image' => 'nginx:latest',
     *             'Env' => ['NGINX_PORT=80']
     *         ]
     *     ],
     *     'Mode' => [
     *         'Replicated' => [
     *             'Replicas' => 3
     *         ]
     *     ]
     * ]);
     * ```
     */
    public function create(array $config, ?string $registryAuth = null): array
    {
        ParameterValidator::validateArray($config, 'config');

        // Validate required Name field
        if (!isset($config['Name'])) {
            throw new InvalidParameterException("Service name is required");
        }
        ParameterValidator::validateString($config['Name'], 'Name');

        // Validate required TaskTemplate field
        if (!isset($config['TaskTemplate'])) {
            throw new InvalidParameterException("TaskTemplate is required");
        }
        ParameterValidator::validateArray($config['TaskTemplate'], 'TaskTemplate');

        // Validate TaskTemplate.ContainerSpec if provided
        if (isset($config['TaskTemplate']['ContainerSpec'])) {
            $this->validateContainerSpec($config['TaskTemplate']['ContainerSpec']);
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

        // Validate Mode if provided
        if (isset($config['Mode'])) {
            $this->validateServiceMode($config['Mode']);
        }

        $headers = [];
        if ($registryAuth) {
            if (!is_string($registryAuth)) {
                throw new InvalidParameterException("Registry auth must be a string");
            }
            $headers['X-Registry-Auth'] = $registryAuth;
        }

        $response = $this->httpClient->post('/services/create', array_merge($this->jsonBody($config), ['headers' => $headers]));
        return $this->getJsonResponse($response);
    }

    /**
     * Inspect a service
     * 
     * Returns detailed information about a specific service.
     * 
     * @param string $id Service ID or name
     * @param bool $insertDefaults Fill in default values for unspecified fields (default: false)
     * 
     * @return array Service object containing detailed information
     * 
     * @throws InvalidParameterException If service ID is invalid
     * @throws DockerException If the service is not found or API request fails
     */
    public function inspect(string $id, bool $insertDefaults = false): array
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');

        $query = $this->buildQuery(['insertDefaults' => $insertDefaults]);
        $response = $this->httpClient->get("/services/{$id}" . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Delete a service
     * 
     * Removes a service from the swarm.
     * 
     * @param string $id Service ID or name
     * 
     * @return void
     * 
     * @throws InvalidParameterException If service ID is invalid
     * @throws DockerException If the service is not found or API request fails
     */
    public function delete(string $id): void
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');

        $this->httpClient->delete("/services/{$id}");
    }

    /**
     * Update a service
     * 
     * Updates a service's configuration.
     * 
     * @param string $id Service ID or name
     * @param int $version Current version of the service object being updated
     * @param array $config Updated service specification
     * @param string|null $registryAuth Registry authentication (base64 encoded JSON)
     * @param string $rollback Rollback operation ("previous" to rollback to previous spec)
     * 
     * @return array Update response containing warnings if any
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the service is not found, version mismatch, or API request fails
     */
    public function update(string $id, int $version, array $config, ?string $registryAuth = null, string $rollback = ''): array
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');
        ParameterValidator::validateInteger($version, 'version', 0);
        ParameterValidator::validateArray($config, 'config');

        if ($rollback !== '' && $rollback !== 'previous') {
            throw new InvalidParameterException("Rollback parameter must be empty or 'previous'");
        }

        $query = $this->buildQuery([
            'version' => $version,
            'registryAuthFrom' => $registryAuth,
            'rollback' => $rollback,
        ]);

        $response = $this->httpClient->post("/services/{$id}/update" . $query, $this->jsonBody($config));
        return $this->getJsonResponse($response);
    }

    /**
     * Get service logs
     * 
     * Returns logs from a specific service.
     * 
     * @param string $id Service ID or name
     * @param array $options Log retrieval options (same as TaskClient::logs)
     * 
     * @return StreamInterface Log stream
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the service is not found or API request fails
     */
    public function logs(string $id, array $options = []): StreamInterface
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');
        ParameterValidator::validateArray($options, 'options');

        // Validate boolean options
        $booleanOptions = ['details', 'follow', 'stdout', 'stderr', 'timestamps'];
        foreach ($booleanOptions as $option) {
            if (isset($options[$option]) && !is_bool($options[$option])) {
                throw new InvalidParameterException("Option '{$option}' must be a boolean value");
            }
        }

        $query = $this->buildQuery([
            'details' => $options['details'] ?? null,
            'follow' => $options['follow'] ?? null,
            'stdout' => $options['stdout'] ?? true,
            'stderr' => $options['stderr'] ?? true,
            'since' => $options['since'] ?? null,
            'timestamps' => $options['timestamps'] ?? null,
            'tail' => $options['tail'] ?? 'all',
        ]);
        
        $response = $this->httpClient->get("/services/{$id}/logs" . $query);
        return $this->getStreamResponse($response);
    }

    /**
     * Validate container specification
     */
    private function validateContainerSpec(array $containerSpec): void
    {
        ParameterValidator::validateArray($containerSpec, 'ContainerSpec');

        // Image is required
        if (!isset($containerSpec['Image'])) {
            throw new InvalidParameterException("ContainerSpec.Image is required");
        }
        ParameterValidator::validateString($containerSpec['Image'], 'ContainerSpec.Image');

        // Validate other optional fields
        if (isset($containerSpec['Env'])) {
            ParameterValidator::validateArray($containerSpec['Env'], 'ContainerSpec.Env');
            foreach ($containerSpec['Env'] as $index => $env) {
                if (!is_string($env) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*=.*$/', $env)) {
                    throw new InvalidParameterException("ContainerSpec.Env[{$index}] must be in format 'KEY=value'");
                }
            }
        }
    }

    /**
     * Validate service mode
     */
    private function validateServiceMode(array $mode): void
    {
        ParameterValidator::validateArray($mode, 'Mode');

        $hasReplicated = isset($mode['Replicated']);
        $hasGlobal = isset($mode['Global']);

        if (!$hasReplicated && !$hasGlobal) {
            throw new InvalidParameterException("Mode must specify either 'Replicated' or 'Global'");
        }

        if ($hasReplicated && $hasGlobal) {
            throw new InvalidParameterException("Mode cannot specify both 'Replicated' and 'Global'");
        }

        if ($hasReplicated) {
            ParameterValidator::validateArray($mode['Replicated'], 'Mode.Replicated');
            if (isset($mode['Replicated']['Replicas'])) {
                ParameterValidator::validateInteger($mode['Replicated']['Replicas'], 'Mode.Replicated.Replicas', 0);
            }
        }
    }
}