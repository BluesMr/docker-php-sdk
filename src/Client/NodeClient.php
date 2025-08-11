<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Docker\API\Validator\ParameterValidator;
use Docker\API\Exception\InvalidParameterException;

/**
 * Node operations client
 * 
 * Provides methods for managing Docker Swarm nodes including listing,
 * inspection, updating, and removal operations.
 */
class NodeClient extends BaseClient
{
    /**
     * List nodes
     * 
     * Returns a list of nodes in the swarm. Nodes can be filtered using the filters parameter.
     * 
     * @param array $filters Optional filters to apply to the node list
     *                      Supported filters:
     *                      - id: string - Node ID
     *                      - label: string|array - Label key or key=value pairs
     *                      - membership: string - Node membership (accepted, pending)
     *                      - name: string - Node name
     *                      - node.label: string|array - Node label key or key=value pairs
     *                      - role: string - Node role (manager, worker)
     * 
     * @return array List of node objects, each containing:
     *               - ID: string - Node ID
     *               - Version: array - Object version information
     *               - CreatedAt: string - Creation timestamp
     *               - UpdatedAt: string - Last update timestamp
     *               - Spec: array - Node specification
     *               - Description: array - Node description
     *               - Status: array - Node status
     *               - ManagerStatus: array - Manager status (if manager node)
     * 
     * @throws InvalidParameterException If filters parameter is invalid
     * @throws DockerException If not in swarm mode or API request fails
     * 
     * @example
     * ```php
     * // List all nodes
     * $nodes = $client->list();
     * 
     * // List manager nodes only
     * $managers = $client->list(['role' => 'manager']);
     * 
     * // List nodes with specific label
     * $nodes = $client->list(['label' => 'environment=production']);
     * ```
     */
    public function list(array $filters = []): array
    {
        // Validate filters parameter
        if (!empty($filters)) {
            ParameterValidator::validateArray($filters, 'filters');
            
            // Validate specific filter keys
            $validFilterKeys = ['id', 'label', 'membership', 'name', 'node.label', 'role'];
            foreach (array_keys($filters) as $key) {
                if (!in_array($key, $validFilterKeys, true)) {
                    throw new InvalidParameterException("Invalid filter key '{$key}'. Valid keys are: " . implode(', ', $validFilterKeys));
                }
            }
            
            // Validate membership filter
            if (isset($filters['membership'])) {
                ParameterValidator::validateEnum($filters['membership'], ['accepted', 'pending'], 'membership filter');
            }
            
            // Validate role filter
            if (isset($filters['role'])) {
                ParameterValidator::validateEnum($filters['role'], ['manager', 'worker'], 'role filter');
            }
        }

        $query = $this->buildQuery([
            'filters' => empty($filters) ? null : json_encode($filters),
        ]);
        
        $response = $this->httpClient->get('/nodes' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Inspect a node
     * 
     * Returns detailed information about a specific node.
     * 
     * @param string $id Node ID or name
     * 
     * @return array Node object containing detailed information
     * 
     * @throws InvalidParameterException If node ID is invalid
     * @throws DockerException If the node is not found or API request fails
     */
    public function inspect(string $id): array
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');

        $response = $this->httpClient->get("/nodes/{$id}");
        return $this->getJsonResponse($response);
    }

    /**
     * Delete a node
     * 
     * Removes a node from the swarm.
     * 
     * @param string $id Node ID or name
     * @param bool $force Force removal of the node (default: false)
     * 
     * @return void
     * 
     * @throws InvalidParameterException If node ID is invalid
     * @throws DockerException If the node is not found or API request fails
     */
    public function delete(string $id, bool $force = false): void
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');

        $query = $this->buildQuery(['force' => $force]);
        $this->httpClient->delete("/nodes/{$id}" . $query);
    }

    /**
     * Update a node
     * 
     * Updates a node's configuration.
     * 
     * @param string $id Node ID or name
     * @param int $version Current version of the node object being updated
     * @param array $config Updated node specification
     * 
     * @return void
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the node is not found, version mismatch, or API request fails
     */
    public function update(string $id, int $version, array $config): void
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');
        ParameterValidator::validateInteger($version, 'version', 0);
        ParameterValidator::validateArray($config, 'config');

        $query = $this->buildQuery(['version' => $version]);
        $this->httpClient->post("/nodes/{$id}/update" . $query, $this->jsonBody($config));
    }
}