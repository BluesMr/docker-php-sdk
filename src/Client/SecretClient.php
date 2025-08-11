<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Docker\API\Validator\ParameterValidator;
use Docker\API\Exception\InvalidParameterException;

/**
 * Secret operations client
 * 
 * Provides methods for managing Docker Swarm secrets including creation,
 * inspection, updating, and removal operations.
 */
class SecretClient extends BaseClient
{
    /**
     * List secrets
     * 
     * Returns a list of secrets in the swarm. Secrets can be filtered using the filters parameter.
     * 
     * @param array $filters Optional filters to apply to the secret list
     *                      Supported filters:
     *                      - id: string - Secret ID
     *                      - label: string|array - Label key or key=value pairs
     *                      - name: string - Secret name
     *                      - names: string|array - Secret names
     * 
     * @return array List of secret objects, each containing:
     *               - ID: string - Secret ID
     *               - Version: array - Object version information
     *               - CreatedAt: string - Creation timestamp
     *               - UpdatedAt: string - Last update timestamp
     *               - Spec: array - Secret specification
     *                 - Name: string - Secret name
     *                 - Labels: array - Secret labels
     *                 - Driver: array - Secret driver configuration
     *                 - Templating: array - Secret templating configuration
     * 
     * @throws InvalidParameterException If filters parameter is invalid
     * @throws DockerException If not in swarm mode or API request fails
     * 
     * @example
     * ```php
     * // List all secrets
     * $secrets = $client->list();
     * 
     * // List secrets with specific label
     * $secrets = $client->list(['label' => 'environment=production']);
     * 
     * // List secrets by name
     * $secrets = $client->list(['name' => 'my-secret']);
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
        
        $response = $this->httpClient->get('/secrets' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Create a secret
     * 
     * Creates a new secret with the specified configuration.
     * 
     * @param array $config Secret configuration
     *                     Required fields:
     *                     - Name: string - Secret name
     *                     - Data: string - Secret data (base64 encoded)
     *                     Optional fields:
     *                     - Labels: array - User-defined key/value metadata
     *                     - Driver: array - Secret driver configuration
     *                       - Name: string - Driver name
     *                       - Options: array - Driver options
     *                     - Templating: array - Secret templating configuration
     *                       - Name: string - Template driver name
     *                       - Options: array - Template options
     * 
     * @return array Secret creation response containing:
     *               - ID: string - Secret ID
     * 
     * @throws InvalidParameterException If configuration is invalid
     * @throws DockerException If not in swarm mode or API request fails
     * 
     * @example
     * ```php
     * // Create a simple secret
     * $secret = $client->create([
     *     'Name' => 'my-secret',
     *     'Data' => base64_encode('secret-data'),
     *     'Labels' => [
     *         'environment' => 'production'
     *     ]
     * ]);
     * 
     * // Create a secret with external driver
     * $secret = $client->create([
     *     'Name' => 'external-secret',
     *     'Driver' => [
     *         'Name' => 'external-driver',
     *         'Options' => [
     *             'key' => 'value'
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
            throw new InvalidParameterException("Secret name is required");
        }
        ParameterValidator::validateString($config['Name'], 'Name');

        // Validate secret name format
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $config['Name'])) {
            throw new InvalidParameterException("Secret name must start with alphanumeric character and contain only alphanumeric, underscore, period, or hyphen characters");
        }

        // Validate Data field (required unless using external driver)
        if (isset($config['Data'])) {
            if (!is_string($config['Data'])) {
                throw new InvalidParameterException("Secret data must be a string");
            }
            
            // Validate base64 encoding
            if (base64_encode(base64_decode($config['Data'], true)) !== $config['Data']) {
                throw new InvalidParameterException("Secret data must be base64 encoded");
            }
        } elseif (!isset($config['Driver'])) {
            throw new InvalidParameterException("Secret data is required when not using external driver");
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

        // Validate Driver if provided
        if (isset($config['Driver'])) {
            $this->validateDriverConfig($config['Driver'], 'Driver');
        }

        // Validate Templating if provided
        if (isset($config['Templating'])) {
            $this->validateDriverConfig($config['Templating'], 'Templating');
        }

        $response = $this->httpClient->post('/secrets/create', $this->jsonBody($config));
        return $this->getJsonResponse($response);
    }

    /**
     * Inspect a secret
     * 
     * Returns detailed information about a specific secret.
     * Note: The secret data is not returned for security reasons.
     * 
     * @param string $id Secret ID or name
     * 
     * @return array Secret object containing:
     *               - ID: string - Secret ID
     *               - Version: array - Object version information
     *               - CreatedAt: string - Creation timestamp
     *               - UpdatedAt: string - Last update timestamp
     *               - Spec: array - Secret specification (without data)
     * 
     * @throws InvalidParameterException If secret ID is invalid
     * @throws DockerException If the secret is not found or API request fails
     * 
     * @example
     * ```php
     * $secret = $client->inspect('my-secret');
     * echo "Secret name: " . $secret['Spec']['Name'];
     * echo "Created: " . $secret['CreatedAt'];
     * ```
     */
    public function inspect(string $id): array
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');

        $response = $this->httpClient->get("/secrets/{$id}");
        return $this->getJsonResponse($response);
    }

    /**
     * Delete a secret
     * 
     * Removes a secret from the swarm. The secret cannot be removed if it's in use by a service.
     * 
     * @param string $id Secret ID or name
     * 
     * @return void
     * 
     * @throws InvalidParameterException If secret ID is invalid
     * @throws DockerException If the secret is not found, in use, or API request fails
     * 
     * @example
     * ```php
     * $client->delete('my-secret');
     * ```
     */
    public function delete(string $id): void
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');

        $this->httpClient->delete("/secrets/{$id}");
    }

    /**
     * Update a secret
     * 
     * Updates a secret's configuration. Note: The secret data cannot be updated.
     * 
     * @param string $id Secret ID or name
     * @param int $version Current version of the secret object being updated
     * @param array $config Updated secret specification
     *                     Updatable fields:
     *                     - Labels: array - User-defined key/value metadata
     *                     - Driver: array - Secret driver configuration (if using external driver)
     * 
     * @return void
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the secret is not found, version mismatch, or API request fails
     * 
     * @example
     * ```php
     * // Update secret labels
     * $secret = $client->inspect('my-secret');
     * $version = $secret['Version']['Index'];
     * 
     * $client->update('my-secret', $version, [
     *     'Labels' => [
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

        // Validate Driver if provided
        if (isset($config['Driver'])) {
            $this->validateDriverConfig($config['Driver'], 'Driver');
        }

        // Data cannot be updated
        if (isset($config['Data'])) {
            throw new InvalidParameterException("Secret data cannot be updated");
        }

        $query = $this->buildQuery(['version' => $version]);
        $this->httpClient->post("/secrets/{$id}/update" . $query, $this->jsonBody($config));
    }

    /**
     * Validate driver configuration
     * 
     * @param array $driver Driver configuration to validate
     * @param string $fieldName Field name for error messages
     * @throws InvalidParameterException If driver configuration is invalid
     */
    private function validateDriverConfig(array $driver, string $fieldName): void
    {
        ParameterValidator::validateArray($driver, $fieldName);

        // Name is required
        if (!isset($driver['Name'])) {
            throw new InvalidParameterException("{$fieldName}.Name is required");
        }
        ParameterValidator::validateString($driver['Name'], "{$fieldName}.Name");

        // Validate Options if provided
        if (isset($driver['Options'])) {
            ParameterValidator::validateArray($driver['Options'], "{$fieldName}.Options");
            
            foreach ($driver['Options'] as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    throw new InvalidParameterException("{$fieldName}.Options must be string key-value pairs");
                }
            }
        }
    }
}