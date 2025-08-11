<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Docker\API\Exception\DockerException;
use Psr\Http\Message\StreamInterface;

/**
 * Image operations client
 */
class ImageClient extends BaseClient
{
    /**
     * List images
     * 
     * @param array $options Query parameters
     * @return array List of images
     */
    public function list(array $options = []): array
    {
        $query = $this->buildQuery([
            'all' => $options['all'] ?? null,
            'filters' => isset($options['filters']) ? json_encode($options['filters']) : null,
            'shared-size' => $options['shared-size'] ?? null,
            'digests' => $options['digests'] ?? null,
        ]);

        $response = $this->httpClient->get('/images/json' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Build an image
     * 
     * @param string $dockerfile Dockerfile content or path
     * @param array $options Build options
     * @return StreamInterface Build output stream
     */
    public function build(string $dockerfile, array $options = []): StreamInterface
    {
        $query = $this->buildQuery([
            'dockerfile' => $options['dockerfile'] ?? 'Dockerfile',
            't' => $options['tag'] ?? null,
            'extrahosts' => $options['extrahosts'] ?? null,
            'remote' => $options['remote'] ?? null,
            'q' => $options['quiet'] ?? null,
            'nocache' => $options['nocache'] ?? null,
            'cachefrom' => isset($options['cachefrom']) ? json_encode($options['cachefrom']) : null,
            'pull' => $options['pull'] ?? null,
            'rm' => $options['rm'] ?? true,
            'forcerm' => $options['forcerm'] ?? null,
            'memory' => $options['memory'] ?? null,
            'memswap' => $options['memswap'] ?? null,
            'cpushares' => $options['cpushares'] ?? null,
            'cpusetcpus' => $options['cpusetcpus'] ?? null,
            'cpuperiod' => $options['cpuperiod'] ?? null,
            'cpuquota' => $options['cpuquota'] ?? null,
            'buildargs' => isset($options['buildargs']) ? json_encode($options['buildargs']) : null,
            'shmsize' => $options['shmsize'] ?? null,
            'squash' => $options['squash'] ?? null,
            'labels' => isset($options['labels']) ? json_encode($options['labels']) : null,
            'networkmode' => $options['networkmode'] ?? null,
            'platform' => $options['platform'] ?? null,
            'target' => $options['target'] ?? null,
            'outputs' => $options['outputs'] ?? null,
        ]);

        $body = is_file($dockerfile) ? file_get_contents($dockerfile) : $dockerfile;
        
        $response = $this->httpClient->post('/build' . $query, $this->binaryBody($body, 'application/x-tar'));
        return $this->getStreamResponse($response);
    }

    /**
     * Delete builder cache
     * 
     * @param array $options Prune options
     * @return array Prune response
     */
    public function buildPrune(array $options = []): array
    {
        $query = $this->buildQuery([
            'keep-storage' => $options['keep-storage'] ?? null,
            'all' => $options['all'] ?? null,
            'filters' => isset($options['filters']) ? json_encode($options['filters']) : null,
        ]);

        $response = $this->httpClient->post('/build/prune' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Create an image (pull)
     * 
     * @param string $fromImage Image name
     * @param array $options Pull options
     * @return StreamInterface Pull output stream
     */
    public function create(string $fromImage, array $options = []): StreamInterface
    {
        $query = $this->buildQuery([
            'fromImage' => $fromImage,
            'fromSrc' => $options['fromSrc'] ?? null,
            'repo' => $options['repo'] ?? null,
            'tag' => $options['tag'] ?? null,
            'message' => $options['message'] ?? null,
            'changes' => $options['changes'] ?? null,
            'platform' => $options['platform'] ?? null,
        ]);

        $headers = [];
        if (isset($options['auth'])) {
            $headers['X-Registry-Auth'] = base64_encode(json_encode($options['auth']));
        }

        $response = $this->httpClient->post('/images/create' . $query, ['headers' => $headers]);
        return $this->getStreamResponse($response);
    }

    /**
     * Inspect an image
     * 
     * @param string $name Image name or ID
     * @return array Image details
     */
    public function inspect(string $name): array
    {
        $response = $this->httpClient->get("/images/{$name}/json");
        return $this->getJsonResponse($response);
    }

    /**
     * Get the history of an image
     * 
     * @param string $name Image name or ID
     * @return array Image history
     */
    public function history(string $name): array
    {
        $response = $this->httpClient->get("/images/{$name}/history");
        return $this->getJsonResponse($response);
    }

    /**
     * Push an image
     * 
     * @param string $name Image name
     * @param array $options Push options
     * @return StreamInterface Push output stream
     */
    public function push(string $name, array $options = []): StreamInterface
    {
        $query = $this->buildQuery([
            'tag' => $options['tag'] ?? null,
        ]);

        $headers = [];
        if (isset($options['auth'])) {
            $headers['X-Registry-Auth'] = base64_encode(json_encode($options['auth']));
        }

        $response = $this->httpClient->post("/images/{$name}/push" . $query, ['headers' => $headers]);
        return $this->getStreamResponse($response);
    }

    /**
     * Tag an image
     * 
     * @param string $name Image name or ID
     * @param string $repo Repository name
     * @param string|null $tag Tag name
     * @return void
     */
    public function tag(string $name, string $repo, ?string $tag = null): void
    {
        $query = $this->buildQuery([
            'repo' => $repo,
            'tag' => $tag,
        ]);

        $this->httpClient->post("/images/{$name}/tag" . $query);
    }

    /**
     * Remove an image
     * 
     * @param string $name Image name or ID
     * @param bool $force Force removal
     * @param bool $noprune Don't delete untagged parents
     * @return array Remove response
     */
    public function remove(string $name, bool $force = false, bool $noprune = false): array
    {
        $query = $this->buildQuery([
            'force' => $force,
            'noprune' => $noprune,
        ]);

        $response = $this->httpClient->delete("/images/{$name}" . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Search images
     * 
     * @param string $term Search term
     * @param int|null $limit Maximum results
     * @param array $filters Search filters
     * @return array Search results
     */
    public function search(string $term, ?int $limit = null, array $filters = []): array
    {
        $query = $this->buildQuery([
            'term' => $term,
            'limit' => $limit,
            'filters' => empty($filters) ? null : json_encode($filters),
        ]);

        $response = $this->httpClient->get('/images/search' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Delete unused images
     * 
     * @param array $filters Filters to apply
     * @return array Prune response
     */
    public function prune(array $filters = []): array
    {
        $query = $this->buildQuery([
            'filters' => empty($filters) ? null : json_encode($filters),
        ]);

        $response = $this->httpClient->post('/images/prune' . $query);
        return $this->getJsonResponse($response);
    }

    /**
     * Create a new image from a container
     * 
     * @param string $container Container ID or name
     * @param array $options Commit options
     * @return array Commit response
     */
    public function commit(string $container, array $options = []): array
    {
        $query = $this->buildQuery([
            'container' => $container,
            'repo' => $options['repo'] ?? null,
            'tag' => $options['tag'] ?? null,
            'comment' => $options['comment'] ?? null,
            'author' => $options['author'] ?? null,
            'pause' => $options['pause'] ?? true,
            'changes' => $options['changes'] ?? null,
        ]);

        $body = isset($options['config']) ? $this->jsonBody($options['config']) : [];

        $response = $this->httpClient->post('/commit' . $query, $body);
        return $this->getJsonResponse($response);
    }

    /**
     * Export an image
     * 
     * @param string $name Image name or ID
     * @return StreamInterface Export stream
     */
    public function export(string $name): StreamInterface
    {
        $response = $this->httpClient->get("/images/{$name}/get");
        return $this->getStreamResponse($response);
    }

    /**
     * Export several images
     * 
     * @param array $names Image names or IDs
     * @return StreamInterface Export stream
     */
    public function exportMultiple(array $names): StreamInterface
    {
        $query = $this->buildQuery([
            'names' => implode(',', $names),
        ]);

        $response = $this->httpClient->get('/images/get' . $query);
        return $this->getStreamResponse($response);
    }

    /**
     * Import images
     * 
     * @param string $tarball Tar archive content
     * @param array $options Import options
     * @return StreamInterface Import output stream
     */
    public function import(string $tarball, array $options = []): StreamInterface
    {
        $query = $this->buildQuery([
            'repo' => $options['repo'] ?? null,
            'tag' => $options['tag'] ?? null,
            'message' => $options['message'] ?? null,
            'changes' => $options['changes'] ?? null,
            'platform' => $options['platform'] ?? null,
        ]);

        $response = $this->httpClient->post('/images/load' . $query, $this->binaryBody($tarball, 'application/x-tar'));
        return $this->getStreamResponse($response);
    }
}