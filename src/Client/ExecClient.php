<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Docker\API\Validator\ParameterValidator;
use Docker\API\Exception\InvalidParameterException;
use Psr\Http\Message\StreamInterface;

/**
 * Exec operations client
 * 
 * Provides methods for creating and managing exec instances within containers.
 * Exec instances allow running commands inside running containers.
 */
class ExecClient extends BaseClient
{
    /**
     * Create an exec instance
     * 
     * Creates an exec instance for running commands inside a container.
     * 
     * @param string $containerId Container ID or name
     * @param array $config Exec configuration
     *                     Optional fields:
     *                     - AttachStdin: bool - Attach to stdin (default: false)
     *                     - AttachStdout: bool - Attach to stdout (default: true)
     *                     - AttachStderr: bool - Attach to stderr (default: true)
     *                     - DetachKeys: string - Override detach keys
     *                     - Tty: bool - Allocate a pseudo-TTY (default: false)
     *                     - Env: array - Environment variables
     *                     - Cmd: array - Command to run (required)
     *                     - Privileged: bool - Run as privileged (default: false)
     *                     - User: string - User to run as
     *                     - WorkingDir: string - Working directory
     * 
     * @return array Exec creation response containing:
     *               - Id: string - Exec instance ID
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the container is not found or API request fails
     * 
     * @example
     * ```php
     * // Create exec instance to run a simple command
     * $exec = $client->create('my-container', [
     *     'Cmd' => ['ls', '-la', '/tmp'],
     *     'AttachStdout' => true,
     *     'AttachStderr' => true
     * ]);
     * 
     * // Create exec instance with TTY and environment variables
     * $exec = $client->create('my-container', [
     *     'Cmd' => ['/bin/bash'],
     *     'Tty' => true,
     *     'AttachStdin' => true,
     *     'AttachStdout' => true,
     *     'AttachStderr' => true,
     *     'Env' => ['PATH=/usr/local/bin:/usr/bin:/bin'],
     *     'User' => 'root',
     *     'WorkingDir' => '/app'
     * ]);
     * ```
     */
    public function create(string $containerId, array $config): array
    {
        ParameterValidator::validateString($containerId, 'containerId');
        ParameterValidator::validateId($containerId, 'containerId');
        ParameterValidator::validateArray($config, 'config');

        // Validate required Cmd field
        if (!isset($config['Cmd'])) {
            throw new InvalidParameterException("Command (Cmd) is required");
        }
        
        ParameterValidator::validateArray($config['Cmd'], 'Cmd', false);
        
        foreach ($config['Cmd'] as $index => $cmd) {
            if (!is_string($cmd)) {
                throw new InvalidParameterException("Command array element at index {$index} must be a string");
            }
        }

        // Validate boolean fields
        $booleanFields = ['AttachStdin', 'AttachStdout', 'AttachStderr', 'Tty', 'Privileged'];
        foreach ($booleanFields as $field) {
            if (isset($config[$field]) && !is_bool($config[$field])) {
                throw new InvalidParameterException("Field '{$field}' must be a boolean value");
            }
        }

        // Validate string fields
        $stringFields = ['DetachKeys', 'User', 'WorkingDir'];
        foreach ($stringFields as $field) {
            if (isset($config[$field])) {
                if (!is_string($config[$field])) {
                    throw new InvalidParameterException("Field '{$field}' must be a string");
                }
            }
        }

        // Validate Env if provided
        if (isset($config['Env'])) {
            ParameterValidator::validateArray($config['Env'], 'Env');
            
            foreach ($config['Env'] as $index => $env) {
                if (!is_string($env)) {
                    throw new InvalidParameterException("Environment variable at index {$index} must be a string");
                }
                
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*=.*$/', $env)) {
                    throw new InvalidParameterException("Environment variable at index {$index} must be in format 'KEY=value'");
                }
            }
        }

        $response = $this->httpClient->post("/containers/{$containerId}/exec", $this->jsonBody($config));
        return $this->getJsonResponse($response);
    }

    /**
     * Start an exec instance
     * 
     * Starts an exec instance and returns the output stream.
     * 
     * @param string $execId Exec instance ID
     * @param array $config Start configuration
     *                     Optional fields:
     *                     - Detach: bool - Detach from the command (default: false)
     *                     - Tty: bool - Allocate a pseudo-TTY (default: false)
     * 
     * @return StreamInterface Output stream from the exec instance
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the exec instance is not found or API request fails
     * 
     * @example
     * ```php
     * // Start exec instance and get output
     * $stream = $client->start($execId, ['Detach' => false]);
     * 
     * while (!$stream->eof()) {
     *     echo $stream->read(1024);
     * }
     * 
     * // Start exec instance in detached mode
     * $client->start($execId, ['Detach' => true]);
     * ```
     */
    public function start(string $execId, array $config = []): StreamInterface
    {
        ParameterValidator::validateString($execId, 'execId');
        ParameterValidator::validateArray($config, 'config');

        // Validate boolean fields
        $booleanFields = ['Detach', 'Tty'];
        foreach ($booleanFields as $field) {
            if (isset($config[$field]) && !is_bool($config[$field])) {
                throw new InvalidParameterException("Field '{$field}' must be a boolean value");
            }
        }

        $response = $this->httpClient->post("/exec/{$execId}/start", $this->jsonBody($config));
        return $this->getStreamResponse($response);
    }

    /**
     * Resize an exec instance TTY
     * 
     * Resizes the TTY session used by an exec instance.
     * 
     * @param string $execId Exec instance ID
     * @param int $height TTY height in characters
     * @param int $width TTY width in characters
     * 
     * @return void
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the exec instance is not found or API request fails
     * 
     * @example
     * ```php
     * // Resize TTY to 80x24
     * $client->resize($execId, 24, 80);
     * ```
     */
    public function resize(string $execId, int $height, int $width): void
    {
        ParameterValidator::validateString($execId, 'execId');
        ParameterValidator::validateInteger($height, 'height', 1, 1000);
        ParameterValidator::validateInteger($width, 'width', 1, 1000);

        $query = $this->buildQuery(['h' => $height, 'w' => $width]);
        $this->httpClient->post("/exec/{$execId}/resize" . $query);
    }

    /**
     * Inspect an exec instance
     * 
     * Returns detailed information about an exec instance.
     * 
     * @param string $execId Exec instance ID
     * 
     * @return array Exec instance details containing:
     *               - CanRemove: bool - Whether the exec instance can be removed
     *               - DetachKeys: string - Detach keys
     *               - ID: string - Exec instance ID
     *               - Running: bool - Whether the exec instance is running
     *               - ExitCode: int|null - Exit code (null if still running)
     *               - ProcessConfig: array - Process configuration
     *                 - Privileged: bool - Whether running as privileged
     *                 - User: string - User running the process
     *                 - Tty: bool - Whether TTY is allocated
     *                 - Entrypoint: string - Entrypoint
     *                 - Arguments: array - Command arguments
     *               - OpenStdin: bool - Whether stdin is open
     *               - OpenStderr: bool - Whether stderr is open
     *               - OpenStdout: bool - Whether stdout is open
     *               - ContainerID: string - Container ID
     *               - Pid: int - Process ID
     * 
     * @throws InvalidParameterException If exec ID is invalid
     * @throws DockerException If the exec instance is not found or API request fails
     * 
     * @example
     * ```php
     * $execInfo = $client->inspect($execId);
     * 
     * if ($execInfo['Running']) {
     *     echo "Exec instance is still running (PID: {$execInfo['Pid']})";
     * } else {
     *     echo "Exec instance finished with exit code: {$execInfo['ExitCode']}";
     * }
     * ```
     */
    public function inspect(string $execId): array
    {
        ParameterValidator::validateString($execId, 'execId');

        $response = $this->httpClient->get("/exec/{$execId}/json");
        return $this->getJsonResponse($response);
    }
}