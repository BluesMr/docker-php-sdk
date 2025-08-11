<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Docker\API\Validator\ParameterValidator;
use Docker\API\Exception\InvalidParameterException;

/**
 * Config operations client
 * 
 * Provides methods for managing Docker Swarm configs including creation,
 * inspection, updating, and removal operations.
 */
class ConfigClient extends BaseClient
{
    /**
     * List configs
     * 
     * Returns a list of configs in the swarm. Configs can be filtered using the filters parameter.
     * 
     * @param array $filters Optional filters to apply to the config list
     *                      Supported filters:
     *                      - id: string - Config ID
     *                      - label: string|array - Label key or key=value pairs
     *                      - name: string - Config name
     *                      - names: string|array - Config names
     * 
     * @return array List of config objects, each containing:
     *               - ID: string - Config ID
     *               - Version: array - Object version information
     *               - CreatedAt: string - Creation timestamp
     *               - UpdatedAt: string - Last update timestamp
     *               - Spec: array - Config specification
     *                 - Name: string - Config name
     *                 - Labels: array - Config labels
     *                 - Data: string - Config data (base64 encoded)
     *                 - Templating: array - Config templating configuration
     * 
     * @throws InvalidParameterException If filters parameter is invalid
     * @throws DockerException If not in swarm mode or API request fails
     * 
     * @example
     * ```php
     * // List all configs
     * $configs = $client->list();
     * 
     * // List configs with specific label
     * $configs = $client->list(['label' => 'environment=production']);
     * 
     * // List configs by name
     * $configs = $client->list(['name' => 'my-config']);
     * ```
     */
    public function list(array $filters = []): array
    {
        // Validate filters parameter
        if (!empty($filters)) {
            ParameterValidator::validateArray($filters, 'filters');
            
            // Validate specific filter keys
            $validFilterKeys = ['id', 'label', 'name', 'names'];
            foreach (array_keys($filters) as $key) {
                if (!in_array($key, $validFilterKeys, true)) {
                    throw new InvalidParameterException("Invalid filter key '{$key}'. Valid keys are: " . implode(', ', $validFilterKeys));
                }
            }
        }

        $query = $this->buildQuery([
            'filters' => empty($filters) ? null : json_encode($filters),
        ]);
        
        $response = $this->httpClient->get('/configs' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Create a config
     * 
     * Creates a new config with the specified configuration.
     * 
     * @param array $config Config configuration
     *                     Required fields:
     *                     - Name: string - Config name
     *                     - Data: string - Config data (base64 encoded)
     *                     Optional fields:
     *                     - Labels: array - User-defined key/value metadata
     *                     - Templating: array - Config templating configuration
     *                       - Name: string - Template driver name
     *                       - Options: array - Template options
     * 
     * @return array Config creation response containing:
     *               - ID: string - Config ID
     * 
     * @throws InvalidParameterException If configuration is invalid
     * @throws DockerException If not in swarm mode or API request fails
     * 
     * @example
     * ```php
     * // Create a simple config
     * $config = $client->create([
     *     'Name' => 'nginx-config',
     *     'Data' => base64_encode('server { listen 80; }'),
     *     'Labels' => [
     *         'service' => 'nginx',
     *         'environment' => 'production'
     *     ]
     * ]);
     * 
     * // Create a config with templating
     * $config = $client->create([
     *     'Name' => 'app-config',
     *     'Data' => base64_encode('database_host={{ .Service.Name }}-db'),
     *     'Templating' => [
     *         'Name' => 'golang',
     *         'Options' => [
     *             'delimiter' => '{{}}'
     *         ]
     *     ]
     * ]);
     * ```
     */
    public function create(array $config): array
    {
        ParameterValidator::validateArray($config, 'config');

        // Validate required Name field
        if (!isset($config['Name'])) {
            throw new InvalidParameterException("Config name is required");
        }
        ParameterValidator::validateString($config['Name'], 'Name');

        // Validate config name format
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $config['Name'])) {
            throw new InvalidParameterException("Config name must start with alphanumeric character and contain only alphanumeric, underscore, period, or hyphen characters");
        }

        // Validate required Data field
        if (!isset($config['Data'])) {
            throw new InvalidParameterException("Config data is required");
        }
        
        if (!is_string($config['Data'])) {
            throw new InvalidParameterException("Config data must be a string");
        }
        
        // Validate base64 encoding
        if (base64_encode(base64_decode($config['Data'], true)) !== $config['Data']) {
            throw new InvalidParameterException("Config data must be base64 encoded");
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

        // Validate Templating if provided
        if (isset($config['Templating'])) {
            $this->validateTemplatingConfig($config['Templating']);
        }

        $response = $this->httpClient->post('/configs/create', $this->jsonBody($config));
        return $this->getJsonResponse($response);
    }

    /**
     * Inspect a config
     * 
     * Returns detailed information about a specific config including its data.
     * 
     * @param string $id Config ID or name
     * 
     * @return array Config object containing:
     *               - ID: string - Config ID
     *               - Version: array - Object version information
     *               - CreatedAt: string - Creation timestamp
     *               - UpdatedAt: string - Last update timestamp
     *               - Spec: array - Config specification (including data)
     * 
     * @throws InvalidParameterException If config ID is invalid
     * @throws DockerException If the config is not found or API request fails
     * 
     * @example
     * ```php
     * $config = $client->inspect('nginx-config');
     * echo "Config name: " . $config['Spec']['Name'];
     * echo "Config data: " . base64_decode($config['Spec']['Data']);
     * ```
     */
    public function inspect(string $id): array
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');

        $response = $this->httpClient->get("/configs/{$id}");
        return $this->getJsonResponse($response);
    }

    /**
     * Delete a config
     * 
     * Removes a config from the swarm. The config cannot be removed if it's in use by a service.
     * 
     * @param string $id Config ID or name
     * 
     * @return void
     * 
     * @throws InvalidParameterException If config ID is invalid
     * @throws DockerException If the config is not found, in use, or API request fails
     * 
     * @example
     * ```php
     * $client->delete('nginx-config');
     * ```
     */
    public function delete(string $id): void
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');

        $this->httpClient->delete("/configs/{$id}");
    }

    /**
     * Update a config
     * 
     * Updates a config's configuration. Note: The config data cannot be updated.
     * 
     * @param string $id Config ID or name
     * @param int $version Current version of the config object being updated
     * @param array $config Updated config specification
     *                     Updatable fields:
     *                     - Labels: array - User-defined key/value metadata
     *                     - Templating: array - Config templating configuration
     * 
     * @return void
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the config is not found, version mismatch, or API request fails
     * 
     * @example
     * ```php
     * // Update config labels
     * $config = $client->inspect('nginx-config');
     * $version = $config['Version']['Index'];
     * 
     * $client->update('nginx-config', $version, [
     *     'Labels' => [
     *         'service' => 'nginx',
     *         'environment' => 'staging',
     *         'updated' => date('Y-m-d')
     *     ]
     * ]);
     * ```
     */
    public function update(string $id, int $version, array $config): void
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');
        ParameterValidator::validateInteger($version, 'version', 0);
        ParameterValidator::validateArray($config, 'config');

        // Validate Labels if provided
        if (isset($config['Labels'])) {
            ParameterValidator::validateArray($config['Labels'], 'Labels');
            
            foreach ($config['Labels'] as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    throw new InvalidParameterException("Labels must be string key-value pairs");
                }
            }
        }

        // Validate Templating if provided
        if (isset($config['Templating'])) {
            $this->validateTemplatingConfig($config['Templating']);
        }

        // Data cannot be updated
        if (isset($config['Data'])) {
            throw new InvalidParameterException("Config data cannot be updated");
        }

        $query = $this->buildQuery(['version' => $version]);
        $this->httpClient->post("/configs/{$id}/update" . $query, $this->jsonBody($config));
    }

    /**
     * Validate templating configuration
     * 
     * @param array $templating Templating configuration to validate
     * @throws InvalidParameterException If templating configuration is invalid
     */
    private function validateTemplatingConfig(array $templating): void
    {
        ParameterValidator::validateArray($templating, 'Templating');

        // Name is required
        if (!isset($templating['Name'])) {
            throw new InvalidParameterException("Templating.Name is required");
        }
        ParameterValidator::validateString($templating['Name'], 'Templating.Name');

        // Validate supported templating engines
        $supportedEngines = ['golang'];
        if (!in_array($templating['Name'], $supportedEngines, true)) {
            throw new InvalidParameterException("Unsupported templating engine. Supported engines: " . implode(', ', $supportedEngines));
        }

        // Validate Options if provided
        if (isset($templating['Options'])) {
            ParameterValidator::validateArray($templating['Options'], 'Templating.Options');
            
            foreach ($templating['Options'] as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    throw new InvalidParameterException("Templating.Options must be string key-value pairs");
                }
            }
        }
    }
}