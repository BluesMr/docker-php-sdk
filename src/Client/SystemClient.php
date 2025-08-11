<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Psr\Http\Message\StreamInterface;

/**
 * System operations client
 */
class SystemClient extends BaseClient
{
    /**
     * Check auth configuration
     * 
     * @param array $authConfig Authentication configuration
     * @return array Auth response
     */
    public function auth(array $authConfig): array
    {
        $response = $this->httpClient->post('/auth', $this->jsonBody($authConfig));
        return $this->getJsonResponse($response);
    }

    /**
     * Get system information
     * 
     * @return array System information
     */
    public function info(): array
    {
        $response = $this->httpClient->get('/info');
        return $this->getJsonResponse($response);
    }

    /**
     * Get version information
     * 
     * @return array Version information
     */
    public function version(): array
    {
        $response = $this->httpClient->get('/version');
        return $this->getJsonResponse($response);
    }

    /**
     * Ping the Docker daemon
     * 
     * @return string Ping response
     */
    public function ping(): string
    {
        $response = $this->httpClient->get('/_ping');
        return $this->getStringResponse($response);
    }

    /**
     * Ping the Docker daemon (HEAD request)
     * 
     * @return array Response headers
     */
    public function pingHead(): array
    {
        $response = $this->httpClient->head('/_ping');
        return $response->getHeaders();
    }

    /**
     * Monitor events
     * 
     * @param array $options Event options
     * @return StreamInterface Event stream
     */
    public function events(array $options = []): StreamInterface
    {
        $query = $this->buildQuery([
            'since' => $options['since'] ?? null,
            'until' => $options['until'] ?? null,
            'filters' => isset($options['filters']) ? json_encode($options['filters']) : null,
        ]);

        $response = $this->httpClient->get('/events' . $query);
        return $this->getStreamResponse($response);
    }

    /**
     * Get data usage information
     * 
     * @param array $type Data types to include
     * @return array Data usage information
     */
    public function df(array $type = []): array
    {
        $query = $this->buildQuery([
            'type' => empty($type) ? null : implode(',', $type),
        ]);

        $response = $this->httpClient->get('/system/df' . $query);
        return $this->getJsonResponse($response);
    }
}