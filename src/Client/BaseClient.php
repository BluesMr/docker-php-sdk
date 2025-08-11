<?php

declare(strict_types=1);

namespace Docker\API\Client;

use Docker\API\Http\HttpClient;
use Psr\Http\Message\ResponseInterface;

/**
 * Base client class for all Docker API clients
 */
abstract class BaseClient
{
    protected HttpClient $httpClient;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Build query string from parameters
     */
    protected function buildQuery(array $params): string
    {
        $filtered = array_filter($params, fn($value) => $value !== null && $value !== '');
        return empty($filtered) ? '' : '?' . http_build_query($filtered);
    }

    /**
     * Prepare JSON body for request
     */
    protected function jsonBody(array $data): array
    {
        return [
            'json' => $data,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ];
    }

    /**
     * Prepare form data body for request
     */
    protected function formBody(array $data): array
    {
        return [
            'form_params' => $data,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ];
    }

    /**
     * Prepare multipart body for request
     */
    protected function multipartBody(array $data): array
    {
        return [
            'multipart' => $data
        ];
    }

    /**
     * Prepare binary body for request
     */
    protected function binaryBody(string $data, string $contentType = 'application/octet-stream'): array
    {
        return [
            'body' => $data,
            'headers' => [
                'Content-Type' => $contentType
            ]
        ];
    }

    /**
     * Get JSON response as array
     */
    protected function getJsonResponse(ResponseInterface $response): array
    {
        return $this->httpClient->getJsonResponse($response);
    }

    /**
     * Get response as string
     */
    protected function getStringResponse(ResponseInterface $response): string
    {
        return $this->httpClient->getStringResponse($response);
    }

    /**
     * Get response as stream
     */
    protected function getStreamResponse(ResponseInterface $response): \Psr\Http\Message\StreamInterface
    {
        return $this->httpClient->getStreamResponse($response);
    }

    /**
     * Check if response is successful
     */
    protected function isSuccessful(ResponseInterface $response): bool
    {
        $statusCode = $response->getStatusCode();
        return $statusCode >= 200 && $statusCode < 300;
    }
}