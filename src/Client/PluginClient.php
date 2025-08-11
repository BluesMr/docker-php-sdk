<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Docker\API\Validator\ParameterValidator;
use Docker\API\Exception\InvalidParameterException;
use Psr\Http\Message\StreamInterface;

/**
 * Plugin operations client
 * 
 * Provides methods for managing Docker plugins including installation,
 * configuration, enabling/disabling, and removal operations.
 */
class PluginClient extends BaseClient
{
    /**
     * List plugins
     * 
     * Returns a list of plugins installed on the Docker daemon.
     * 
     * @param array $filters Optional filters to apply to the plugin list
     *                      Supported filters:
     *                      - capability: string - Plugin capability
     *                      - enable: bool - Plugin enabled state
     * 
     * @return array List of plugin objects, each containing:
     *               - Id: string - Plugin ID
     *               - Name: string - Plugin name
     *               - Enabled: bool - Whether plugin is enabled
     *               - Settings: array - Plugin settings
     *               - PluginReference: string - Plugin reference
     *               - Config: array - Plugin configuration
     * 
     * @throws InvalidParameterException If filters parameter is invalid
     * @throws DockerException If API request fails
     * 
     * @example
     * ```php
     * // List all plugins
     * $plugins = $client->list();
     * 
     * // List enabled plugins only
     * $plugins = $client->list(['enable' => true]);
     * 
     * // List plugins with specific capability
     * $plugins = $client->list(['capability' => 'volumedriver']);
     * ```
     */
    public function list(array $filters = []): array
    {
        // Validate filters parameter
        if (!empty($filters)) {
            ParameterValidator::validateArray($filters, 'filters');
            
            // Validate specific filter keys
            $validFilterKeys = ['capability', 'enable'];
            foreach (array_keys($filters) as $key) {
                if (!in_array($key, $validFilterKeys, true)) {
                    throw new InvalidParameterException("Invalid filter key '{$key}'. Valid keys are: " . implode(', ', $validFilterKeys));
                }
            }
            
            // Validate enable filter
            if (isset($filters['enable']) && !is_bool($filters['enable'])) {
                throw new InvalidParameterException("Filter 'enable' must be a boolean value");
            }
        }

        $query = $this->buildQuery([
            'filters' => empty($filters) ? null : json_encode($filters),
        ]);
        
        $response = $this->httpClient->get('/plugins' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Get plugin privileges
     * 
     * Returns the privileges required by a plugin before installation.
     * 
     * @param string $remote Plugin name or reference
     * 
     * @return array List of privilege objects, each containing:
     *               - Name: string - Privilege name
     *               - Description: string - Privilege description
     *               - Value: array - Privilege values
     * 
     * @throws InvalidParameterException If remote parameter is invalid
     * @throws DockerException If API request fails
     * 
     * @example
     * ```php
     * $privileges = $client->getPrivileges('vieux/sshfs:latest');
     * 
     * foreach ($privileges as $privilege) {
     *     echo "Privilege: {$privilege['Name']}\n";
     *     echo "Description: {$privilege['Description']}\n";
     * }
     * ```
     */
    public function getPrivileges(string $remote): array
    {
        ParameterValidator::validateString($remote, 'remote');

        $query = $this->buildQuery(['remote' => $remote]);
        $response = $this->httpClient->get('/plugins/privileges' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Install a plugin
     * 
     * Pulls and installs a plugin from a registry.
     * 
     * @param string $remote Plugin name or reference
     * @param array $config Plugin configuration
     *                     Optional fields:
     *                     - Disabled: bool - Install plugin in disabled state
     *                     - Env: array - Environment variables
     *                     - Args: array - Plugin arguments
     *                     - Privileges: array - Plugin privileges to grant
     * @param string|null $registryAuth Registry authentication (base64 encoded JSON)
     * 
     * @return StreamInterface Installation progress stream
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If API request fails
     * 
     * @example
     * ```php
     * // Install plugin with default settings
     * $stream = $client->pull('vieux/sshfs:latest');
     * 
     * // Install plugin with custom configuration
     * $stream = $client->pull('vieux/sshfs:latest', [
     *     'Disabled' => false,
     *     'Env' => ['DEBUG=1'],
     *     'Args' => ['--verbose']
     * ]);
     * 
     * while (!$stream->eof()) {
     *     echo $stream->read(1024);
     * }
     * ```
     */
    public function pull(string $remote, array $config = [], ?string $registryAuth = null): StreamInterface
    {
        ParameterValidator::validateString($remote, 'remote');
        ParameterValidator::validateArray($config, 'config');

        // Validate Disabled field if provided
        if (isset($config['Disabled']) && !is_bool($config['Disabled'])) {
            throw new InvalidParameterException("Config field 'Disabled' must be a boolean value");
        }

        // Validate Env field if provided
        if (isset($config['Env'])) {
            ParameterValidator::validateArray($config['Env'], 'Env');
            
            foreach ($config['Env'] as $index => $env) {
                if (!is_string($env)) {
                    throw new InvalidParameterException("Environment variable at index {$index} must be a string");
                }
            }
        }

        // Validate Args field if provided
        if (isset($config['Args'])) {
            ParameterValidator::validateArray($config['Args'], 'Args');
            
            foreach ($config['Args'] as $index => $arg) {
                if (!is_string($arg)) {
                    throw new InvalidParameterException("Argument at index {$index} must be a string");
                }
            }
        }

        // Validate Privileges field if provided
        if (isset($config['Privileges'])) {
            ParameterValidator::validateArray($config['Privileges'], 'Privileges');
        }

        $query = $this->buildQuery(['remote' => $remote]);
        $headers = [];
        if ($registryAuth) {
            if (!is_string($registryAuth)) {
                throw new InvalidParameterException("Registry auth must be a string");
            }
            $headers['X-Registry-Auth'] = $registryAuth;
        }

        $response = $this->httpClient->post('/plugins/pull' . $query, array_merge($this->jsonBody($config), ['headers' => $headers]));
        return $this->getStreamResponse($response);
    }

    /**
     * Inspect a plugin
     * 
     * Returns detailed information about a specific plugin.
     * 
     * @param string $name Plugin name or ID
     * 
     * @return array Plugin object containing detailed information
     * 
     * @throws InvalidParameterException If plugin name is invalid
     * @throws DockerException If the plugin is not found or API request fails
     * 
     * @example
     * ```php
     * $plugin = $client->inspect('vieux/sshfs:latest');
     * 
     * echo "Plugin ID: " . $plugin['Id'];
     * echo "Plugin enabled: " . ($plugin['Enabled'] ? 'Yes' : 'No');
     * ```
     */
    public function inspect(string $name): array
    {
        ParameterValidator::validateString($name, 'name');

        $response = $this->httpClient->get("/plugins/{$name}/json");
        return $this->getJsonResponse($response);
    }

    /**
     * Remove a plugin
     * 
     * Removes a plugin from the Docker daemon.
     * 
     * @param string $name Plugin name or ID
     * @param bool $force Force removal of the plugin (default: false)
     * 
     * @return array Removal response (may contain warnings)
     * 
     * @throws InvalidParameterException If plugin name is invalid
     * @throws DockerException If the plugin is not found, in use, or API request fails
     * 
     * @example
     * ```php
     * // Remove plugin gracefully
     * $result = $client->remove('vieux/sshfs:latest');
     * 
     * // Force remove plugin
     * $result = $client->remove('vieux/sshfs:latest', true);
     * ```
     */
    public function remove(string $name, bool $force = false): array
    {
        ParameterValidator::validateString($name, 'name');

        $query = $this->buildQuery(['force' => $force]);
        $response = $this->httpClient->delete("/plugins/{$name}" . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Enable a plugin
     * 
     * Enables a plugin that was previously disabled.
     * 
     * @param string $name Plugin name or ID
     * @param int $timeout Timeout in seconds to wait for plugin to start (default: 0 = no timeout)
     * 
     * @return void
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the plugin is not found or API request fails
     * 
     * @example
     * ```php
     * // Enable plugin with default timeout
     * $client->enable('vieux/sshfs:latest');
     * 
     * // Enable plugin with 30 second timeout
     * $client->enable('vieux/sshfs:latest', 30);
     * ```
     */
    public function enable(string $name, int $timeout = 0): void
    {
        ParameterValidator::validateString($name, 'name');
        ParameterValidator::validateInteger($timeout, 'timeout', 0);

        $query = $this->buildQuery(['timeout' => $timeout]);
        $this->httpClient->post("/plugins/{$name}/enable" . $query);
    }

    /**
     * Disable a plugin
     * 
     * Disables a plugin that is currently enabled.
     * 
     * @param string $name Plugin name or ID
     * @param bool $force Force disable the plugin (default: false)
     * 
     * @return void
     * 
     * @throws InvalidParameterException If plugin name is invalid
     * @throws DockerException If the plugin is not found or API request fails
     * 
     * @example
     * ```php
     * // Disable plugin gracefully
     * $client->disable('vieux/sshfs:latest');
     * 
     * // Force disable plugin
     * $client->disable('vieux/sshfs:latest', true);
     * ```
     */
    public function disable(string $name, bool $force = false): void
    {
        ParameterValidator::validateString($name, 'name');

        $query = $this->buildQuery(['force' => $force]);
        $this->httpClient->post("/plugins/{$name}/disable" . $query);
    }

    /**
     * Upgrade a plugin
     * 
     * Upgrades a plugin to a newer version.
     * 
     * @param string $name Plugin name or ID
     * @param string $remote New plugin reference to upgrade to
     * @param array $config Plugin configuration for upgrade
     * @param string|null $registryAuth Registry authentication (base64 encoded JSON)
     * 
     * @return StreamInterface Upgrade progress stream
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the plugin is not found or API request fails
     * 
     * @example
     * ```php
     * $stream = $client->upgrade('vieux/sshfs:latest', 'vieux/sshfs:v2.0');
     * 
     * while (!$stream->eof()) {
     *     echo $stream->read(1024);
     * }
     * ```
     */
    public function upgrade(string $name, string $remote, array $config = [], ?string $registryAuth = null): StreamInterface
    {
        ParameterValidator::validateString($name, 'name');
        ParameterValidator::validateString($remote, 'remote');
        ParameterValidator::validateArray($config, 'config');

        $query = $this->buildQuery(['remote' => $remote]);
        $headers = [];
        if ($registryAuth) {
            if (!is_string($registryAuth)) {
                throw new InvalidParameterException("Registry auth must be a string");
            }
            $headers['X-Registry-Auth'] = $registryAuth;
        }

        $response = $this->httpClient->post("/plugins/{$name}/upgrade" . $query, array_merge($this->jsonBody($config), ['headers' => $headers]));
        return $this->getStreamResponse($response);
    }

    /**
     * Create a plugin
     * 
     * Creates a plugin from a tar archive containing the plugin's root filesystem and configuration.
     * 
     * @param string $name Plugin name
     * @param string $tarContext Tar archive content containing plugin files
     * 
     * @return void
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If API request fails
     * 
     * @example
     * ```php
     * $tarContent = file_get_contents('plugin.tar');
     * $client->create('my-plugin:latest', $tarContent);
     * ```
     */
    public function create(string $name, string $tarContext): void
    {
        ParameterValidator::validateString($name, 'name');
        ParameterValidator::validateNotEmpty($tarContext, 'tarContext');

        $query = $this->buildQuery(['name' => $name]);
        $this->httpClient->post('/plugins/create' . $query, $this->binaryBody($tarContext, 'application/x-tar'));
    }

    /**
     * Push a plugin
     * 
     * Pushes a plugin to a registry.
     * 
     * @param string $name Plugin name or ID
     * 
     * @return StreamInterface Push progress stream
     * 
     * @throws InvalidParameterException If plugin name is invalid
     * @throws DockerException If the plugin is not found or API request fails
     * 
     * @example
     * ```php
     * $stream = $client->push('my-plugin:latest');
     * 
     * while (!$stream->eof()) {
     *     echo $stream->read(1024);
     * }
     * ```
     */
    public function push(string $name): StreamInterface
    {
        ParameterValidator::validateString($name, 'name');

        $response = $this->httpClient->post("/plugins/{$name}/push");
        return $this->getStreamResponse($response);
    }

    /**
     * Configure a plugin
     * 
     * Updates the configuration of a plugin.
     * 
     * @param string $name Plugin name or ID
     * @param array $config Plugin configuration settings
     *                     Array of setting name-value pairs
     * 
     * @return void
     * 
     * @throws InvalidParameterException If parameters are invalid
     * @throws DockerException If the plugin is not found or API request fails
     * 
     * @example
     * ```php
     * $client->configure('vieux/sshfs:latest', [
     *     'DEBUG' => '1',
     *     'MOUNT_OPTIONS' => 'allow_other'
     * ]);
     * ```
     */
    public function configure(string $name, array $config): void
    {
        ParameterValidator::validateString($name, 'name');
        ParameterValidator::validateArray($config, 'config');

        // Validate that all config values are strings
        foreach ($config as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidParameterException("Configuration keys must be strings");
            }
            
            if (!is_string($value)) {
                throw new InvalidParameterException("Configuration values must be strings");
            }
        }

        $this->httpClient->post("/plugins/{$name}/set", $this->jsonBody($config));
    }
}