<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Docker\API\Validator\ParameterValidator;
use Docker\API\Exception\InvalidParameterException;
use Psr\Http\Message\StreamInterface;

/**
 * Task operations client
 * 
 * Provides methods for managing Docker Swarm tasks. Tasks are the atomic
 * scheduling unit of swarm and represent containers running on swarm nodes.
 */
class TaskClient extends BaseClient
{
    /**
     * List tasks
     * 
     * Returns a list of tasks. Tasks can be filtered using the filters parameter.
     * 
     * @param array $filters Optional filters to apply to the task list
     *                      Supported filters:
     *                      - id: string - Task ID
     *                      - label: string|array - Label key or key=value pairs
     *                      - name: string - Task name
     *                      - node: string - Node ID or name
     *                      - service: string - Service ID or name
     *                      - desired-state: string - Task desired state (running, shutdown, accepted)
     * 
     * @return array List of task objects, each containing:
     *               - ID: string - Task ID
     *               - Version: array - Object version information
     *               - CreatedAt: string - Creation timestamp
     *               - UpdatedAt: string - Last update timestamp
     *               - Name: string - Task name
     *               - Labels: array - Task labels
     *               - Spec: array - Task specification
     *               - ServiceID: string - Service ID
     *               - Slot: int - Task slot number
     *               - NodeID: string - Node ID where task is running
     *               - AssignedGenericResources: array - Assigned generic resources
     *               - Status: array - Task status information
     *                 - Timestamp: string - Status timestamp
     *                 - State: string - Task state
     *                 - Message: string - Status message
     *                 - Err: string - Error message
     *                 - ContainerStatus: array - Container status
     *               - DesiredState: string - Desired task state
     *               - NetworksAttachments: array - Network attachments
     *               - GenericResources: array - Generic resources
     * 
     * @throws InvalidParameterException If filters parameter is invalid
     * @throws DockerException If not in swarm mode or API request fails
     * 
     * @example
     * ```php
     * // List all tasks
     * $tasks = $client->list();
     * 
     * // List tasks for a specific service
     * $tasks = $client->list(['service' => 'my-service']);
     * 
     * // List running tasks
     * $tasks = $client->list(['desired-state' => 'running']);
     * 
     * // List tasks on a specific node
     * $tasks = $client->list(['node' => 'node-1']);
     * ```
     */
    public function list(array $filters = []): array
    {
        // Validate filters parameter
        if (!empty($filters)) {
            ParameterValidator::validateArray($filters, 'filters');
            
            // Validate specific filter keys
            $validFilterKeys = ['id', 'label', 'name', 'node', 'service', 'desired-state'];
            foreach (array_keys($filters) as $key) {
                if (!in_array($key, $validFilterKeys, true)) {
                    throw new InvalidParameterException("Invalid filter key '{$key}'. Valid keys are: " . implode(', ', $validFilterKeys));
                }
            }
            
            // Validate desired-state filter
            if (isset($filters['desired-state'])) {
                $validStates = ['running', 'shutdown', 'accepted'];
                ParameterValidator::validateEnum($filters['desired-state'], $validStates, 'desired-state filter');
            }
        }

        $query = $this->buildQuery([
            'filters' => empty($filters) ? null : json_encode($filters),
        ]);
        
        $response = $this->httpClient->get('/tasks' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Inspect a task
     * 
     * Returns detailed information about a specific task.
     * 
     * @param string $id Task ID
     * 
     * @return array Task object containing:
     *               - ID: string - Task ID
     *               - Version: array - Object version information
     *               - CreatedAt: string - Creation timestamp
     *               - UpdatedAt: string - Last update timestamp
     *               - Name: string - Task name
     *               - Labels: array - Task labels
     *               - Spec: array - Task specification
     *                 - ContainerSpec: array - Container specification
     *                 - NetworkAttachmentSpec: array - Network attachment specification
     *                 - Resources: array - Resource requirements
     *                 - RestartPolicy: array - Restart policy
     *                 - Placement: array - Placement constraints
     *                 - ForceUpdate: int - Force update counter
     *                 - Runtime: string - Runtime type
     *                 - Networks: array - Network configurations
     *                 - LogDriver: array - Log driver configuration
     *               - ServiceID: string - Service ID
     *               - Slot: int - Task slot number
     *               - NodeID: string - Node ID where task is running
     *               - AssignedGenericResources: array - Assigned generic resources
     *               - Status: array - Task status information
     *               - DesiredState: string - Desired task state
     *               - NetworksAttachments: array - Network attachments
     *               - GenericResources: array - Generic resources
     * 
     * @throws InvalidParameterException If task ID is invalid
     * @throws DockerException If the task is not found or API request fails
     * 
     * @example
     * ```php
     * $task = $client->inspect('task-id');
     * 
     * echo "Task name: " . $task['Name'];
     * echo "Task state: " . $task['Status']['State'];
     * echo "Running on node: " . $task['NodeID'];
     * ```
     */
    public function inspect(string $id): array
    {
        ParameterValidator::validateString($id, 'id');
        ParameterValidator::validateId($id, 'id');

        $response = $this->httpClient->get("/tasks/{$id}");
        return $this->getJsonResponse($response);
    }

    /**
     * Get task logs
     * 
     * Returns logs from a specific task.
     * 
     * @param string $id Task ID
     * @param array $options Log retrieval options
     *                      Optional fields:
     *                      - details: bool - Show extra details provided to logs (default: false)
     *                      - follow: bool - Follow log output (default: false)
     *                      - stdout: bool - Return stdout logs (default: true)
     *                      - stderr: bool - Return stderr logs (default: true)
     *                      - since: int|string - Show logs since timestamp or relative time
     *                      - until: int|string - Show logs until timestamp or relative time
     *                      - timestamps: bool - Add timestamps to log lines (default: false)
     *                      - tail: string|int - Number of lines to show from end of logs (default: "all")
     * 
     * @return StreamInterface Log stream
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the task is not found or API request fails
     * 
     * @example
     * ```php
     * // Get all logs
     * $logs = $client->logs('task-id');
     * while (!$logs->eof()) {
     *     echo $logs->read(1024);
     * }
     * 
     * // Get last 100 lines with timestamps
     * $logs = $client->logs('task-id', [
     *     'tail' => 100,
     *     'timestamps' => true
     * ]);
     * 
     * // Follow logs in real-time
     * $logs = $client->logs('task-id', [
     *     'follow' => true,
     *     'stdout' => true,
     *     'stderr' => true
     * ]);
     * 
     * // Get logs since specific time
     * $logs = $client->logs('task-id', [
     *     'since' => '2023-01-01T00:00:00Z'
     * ]);
     * ```
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

        // Validate since and until options
        $timeOptions = ['since', 'until'];
        foreach ($timeOptions as $option) {
            if (isset($options[$option])) {
                if (!is_int($options[$option]) && !is_string($options[$option])) {
                    throw new InvalidParameterException("Option '{$option}' must be an integer timestamp or string");
                }
                
                // If string, validate it's a valid timestamp format
                if (is_string($options[$option]) && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $options[$option])) {
                    // Allow relative time formats like "1h", "30m", "10s"
                    if (!preg_match('/^\d+[smhd]$/', $options[$option])) {
                        throw new InvalidParameterException("Option '{$option}' must be a valid timestamp or relative time format");
                    }
                }
            }
        }

        // Validate tail option
        if (isset($options['tail'])) {
            if (!is_string($options['tail']) && !is_int($options['tail'])) {
                throw new InvalidParameterException("Option 'tail' must be a string or integer");
            }
            
            if (is_string($options['tail']) && $options['tail'] !== 'all' && !ctype_digit($options['tail'])) {
                throw new InvalidParameterException("Option 'tail' must be 'all' or a numeric string");
            }
            
            if (is_int($options['tail']) && $options['tail'] < 0) {
                throw new InvalidParameterException("Option 'tail' must be a non-negative integer");
            }
        }

        $query = $this->buildQuery([
            'details' => $options['details'] ?? null,
            'follow' => $options['follow'] ?? null,
            'stdout' => $options['stdout'] ?? true,
            'stderr' => $options['stderr'] ?? true,
            'since' => $options['since'] ?? null,
            'until' => $options['until'] ?? null,
            'timestamps' => $options['timestamps'] ?? null,
            'tail' => $options['tail'] ?? 'all',
        ]);
        
        $response = $this->httpClient->get("/tasks/{$id}/logs" . $query);
        return $this->getStreamResponse($response);
    }
}