<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Docker\API\Model\Container\ContainerCreateRequest;
use Docker\API\Model\Container\ContainerUpdateRequest;
use Docker\API\Exception\DockerException;
use Psr\Http\Message\StreamInterface;

/**
 * Container operations client
 * 
 * Provides methods for all container-related Docker API operations
 */
class ContainerClient extends BaseClient
{
    /**
     * List containers
     * 
     * @param array $options Query parameters
     * @return array List of containers
     */
    public function list(array $options = []): array
    {
        $query = $this->buildQuery([
            'all' => $options['all'] ?? null,
            'limit' => $options['limit'] ?? null,
            'size' => $options['size'] ?? null,
            'filters' => isset($options['filters']) ? json_encode($options['filters']) : null,
        ]);

        $response = $this->httpClient->get('/containers/json' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Create a container
     * 
     * @param ContainerCreateRequest|array $config Container configuration
     * @param string|null $name Container name
     * @return array Container creation response
     */
    public function create($config, ?string $name = null): array
    {
        $query = $this->buildQuery(['name' => $name]);
        
        $body = $config instanceof ContainerCreateRequest 
            ? $config->toArray() 
            : $config;

        $response = $this->httpClient->post('/containers/create' . $query, $this->jsonBody($body));
        return $this->getJsonResponse($response);
    }

    /**
     * Inspect a container
     * 
     * @param string $id Container ID or name
     * @param bool $size Return container size information
     * @return array Container details
     */
    public function inspect(string $id, bool $size = false): array
    {
        $query = $this->buildQuery(['size' => $size]);
        
        $response = $this->httpClient->get("/containers/{$id}/json" . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * List processes running inside a container
     * 
     * @param string $id Container ID or name
     * @param string $psArgs ps command arguments
     * @return array Process list
     */
    public function top(string $id, string $psArgs = '-ef'): array
    {
        $query = $this->buildQuery(['ps_args' => $psArgs]);
        
        $response = $this->httpClient->get("/containers/{$id}/top" . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Get container logs
     * 
     * @param string $id Container ID or name
     * @param array $options Log options
     * @return string Container logs
     */
    public function logs(string $id, array $options = []): string
    {
        $query = $this->buildQuery([
            'follow' => $options['follow'] ?? null,
            'stdout' => $options['stdout'] ?? true,
            'stderr' => $options['stderr'] ?? true,
            'since' => $options['since'] ?? null,
            'until' => $options['until'] ?? null,
            'timestamps' => $options['timestamps'] ?? null,
            'tail' => $options['tail'] ?? 'all',
        ]);

        $response = $this->httpClient->get("/containers/{$id}/logs" . $query);
        return $this->getStringResponse($response);
    }

    /**
     * Get changes on a container's filesystem
     * 
     * @param string $id Container ID or name
     * @return array Filesystem changes
     */
    public function changes(string $id): array
    {
        $response = $this->httpClient->get("/containers/{$id}/changes");
        return $this->getJsonResponse($response);
    }

    /**
     * Export a container
     * 
     * @param string $id Container ID or name
     * @return StreamInterface Container export stream
     */
    public function export(string $id): StreamInterface
    {
        $response = $this->httpClient->get("/containers/{$id}/export");
        return $this->getStreamResponse($response);
    }

    /**
     * Get container stats
     * 
     * @param string $id Container ID or name
     * @param bool $stream Stream stats continuously
     * @param bool $oneShot Get single stats snapshot
     * @return array|StreamInterface Container stats
     */
    public function stats(string $id, bool $stream = false, bool $oneShot = false)
    {
        $query = $this->buildQuery([
            'stream' => $stream,
            'one-shot' => $oneShot,
        ]);

        $response = $this->httpClient->get("/containers/{$id}/stats" . $query);
        
        return $stream ? $this->getStreamResponse($response) : $this->getJsonResponse($response);
    }

    /**
     * Resize a container TTY
     * 
     * @param string $id Container ID or name
     * @param int $height TTY height
     * @param int $width TTY width
     * @return void
     */
    public function resize(string $id, int $height, int $width): void
    {
        $query = $this->buildQuery([
            'h' => $height,
            'w' => $width,
        ]);

        $this->httpClient->post("/containers/{$id}/resize" . $query);
    }

    /**
     * Start a container
     * 
     * @param string $id Container ID or name
     * @param string|null $detachKeys Override detach keys
     * @return void
     */
    public function start(string $id, ?string $detachKeys = null): void
    {
        $query = $this->buildQuery(['detachKeys' => $detachKeys]);
        
        $this->httpClient->post("/containers/{$id}/start" . $query);
    }

    /**
     * Stop a container
     * 
     * @param string $id Container ID or name
     * @param int|null $timeout Seconds to wait before killing
     * @return void
     */
    public function stop(string $id, ?int $timeout = null): void
    {
        $query = $this->buildQuery(['t' => $timeout]);
        
        $this->httpClient->post("/containers/{$id}/stop" . $query);
    }

    /**
     * Restart a container
     * 
     * @param string $id Container ID or name
     * @param int|null $timeout Seconds to wait before killing
     * @return void
     */
    public function restart(string $id, ?int $timeout = null): void
    {
        $query = $this->buildQuery(['t' => $timeout]);
        
        $this->httpClient->post("/containers/{$id}/restart" . $query);
    }

    /**
     * Kill a container
     * 
     * @param string $id Container ID or name
     * @param string $signal Signal to send
     * @return void
     */
    public function kill(string $id, string $signal = 'SIGKILL'): void
    {
        $query = $this->buildQuery(['signal' => $signal]);
        
        $this->httpClient->post("/containers/{$id}/kill" . $query);
    }

    /**
     * Update a container
     * 
     * @param string $id Container ID or name
     * @param ContainerUpdateRequest|array $config Update configuration
     * @return array Update response
     */
    public function update(string $id, $config): array
    {
        $body = $config instanceof ContainerUpdateRequest 
            ? $config->toArray() 
            : $config;

        $response = $this->httpClient->post("/containers/{$id}/update", $this->jsonBody($body));
        return $this->getJsonResponse($response);
    }

    /**
     * Rename a container
     * 
     * @param string $id Container ID or name
     * @param string $name New container name
     * @return void
     */
    public function rename(string $id, string $name): void
    {
        $query = $this->buildQuery(['name' => $name]);
        
        $this->httpClient->post("/containers/{$id}/rename" . $query);
    }

    /**
     * Pause a container
     * 
     * @param string $id Container ID or name
     * @return void
     */
    public function pause(string $id): void
    {
        $this->httpClient->post("/containers/{$id}/pause");
    }

    /**
     * Unpause a container
     * 
     * @param string $id Container ID or name
     * @return void
     */
    public function unpause(string $id): void
    {
        $this->httpClient->post("/containers/{$id}/unpause");
    }

    /**
     * Attach to a container
     * 
     * @param string $id Container ID or name
     * @param array $options Attach options
     * @return StreamInterface Attach stream
     */
    public function attach(string $id, array $options = []): StreamInterface
    {
        $query = $this->buildQuery([
            'detachKeys' => $options['detachKeys'] ?? null,
            'logs' => $options['logs'] ?? null,
            'stream' => $options['stream'] ?? null,
            'stdin' => $options['stdin'] ?? null,
            'stdout' => $options['stdout'] ?? null,
            'stderr' => $options['stderr'] ?? null,
        ]);

        $response = $this->httpClient->post("/containers/{$id}/attach" . $query);
        return $this->getStreamResponse($response);
    }

    /**
     * Wait for a container
     * 
     * @param string $id Container ID or name
     * @param string $condition Wait condition
     * @return array Wait response
     */
    public function wait(string $id, string $condition = 'not-running'): array
    {
        $query = $this->buildQuery(['condition' => $condition]);
        
        $response = $this->httpClient->post("/containers/{$id}/wait" . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Remove a container
     * 
     * @param string $id Container ID or name
     * @param bool $removeVolumes Remove associated volumes
     * @param bool $force Force removal of running container
     * @param bool $removeLinks Remove specified link
     * @return void
     */
    public function remove(string $id, bool $removeVolumes = false, bool $force = false, bool $removeLinks = false): void
    {
        $query = $this->buildQuery([
            'v' => $removeVolumes,
            'force' => $force,
            'link' => $removeLinks,
        ]);

        $this->httpClient->delete("/containers/{$id}" . $query);
    }

    /**
     * Get information about files in a container
     * 
     * @param string $id Container ID or name
     * @param string $path Path to file/directory
     * @return array Archive information
     */
    public function getArchiveInfo(string $id, string $path): array
    {
        $query = $this->buildQuery(['path' => $path]);
        
        $response = $this->httpClient->head("/containers/{$id}/archive" . $query);
        
        // Parse X-Docker-Container-Path-Stat header
        $stat = $response->getHeaderLine('X-Docker-Container-Path-Stat');
        return $stat ? json_decode(base64_decode($stat), true) : [];
    }

    /**
     * Get an archive of a filesystem resource in a container
     * 
     * @param string $id Container ID or name
     * @param string $path Path to file/directory
     * @return StreamInterface Archive stream
     */
    public function getArchive(string $id, string $path): StreamInterface
    {
        $query = $this->buildQuery(['path' => $path]);
        
        $response = $this->httpClient->get("/containers/{$id}/archive" . $query);
        return $this->getStreamResponse($response);
    }

    /**
     * Extract an archive to a directory in a container
     * 
     * @param string $id Container ID or name
     * @param string $path Path to directory
     * @param string $archive Tar archive content
     * @param bool $noOverwriteDirNonDir Don't overwrite directory with non-directory
     * @param bool $copyUIDGID Copy UID/GID maps
     * @return void
     */
    public function putArchive(string $id, string $path, string $archive, bool $noOverwriteDirNonDir = false, bool $copyUIDGID = false): void
    {
        $query = $this->buildQuery([
            'path' => $path,
            'noOverwriteDirNonDir' => $noOverwriteDirNonDir,
            'copyUIDGID' => $copyUIDGID,
        ]);

        $this->httpClient->put("/containers/{$id}/archive" . $query, $this->binaryBody($archive, 'application/x-tar'));
    }

    /**
     * Delete stopped containers
     * 
     * @param array $filters Filters to apply
     * @return array Prune response
     */
    public function prune(array $filters = []): array
    {
        $query = $this->buildQuery([
            'filters' => empty($filters) ? null : json_encode($filters),
        ]);

        $response = $this->httpClient->post('/containers/prune' . $query);
        return $this->getJsonResponse($response);
    }
}