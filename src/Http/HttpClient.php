<?php

declare(strict_types=1);

namespace Docker\API\Http;

use Docker\API\Exception\DockerException;
use Docker\API\Exception\ClientException;
use Docker\API\Exception\ServerException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException as GuzzleClientException;
use GuzzleHttp\Exception\ServerException as GuzzleServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * HTTP client wrapper for Docker API communication
 */
class HttpClient
{
    private GuzzleClient $client;
    private LoggerInterface $logger;

    public function __construct(GuzzleClient $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Send GET request
     */
    public function get(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('GET', $uri, $options);
    }

    /**
     * Send POST request
     */
    public function post(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('POST', $uri, $options);
    }

    /**
     * Send PUT request
     */
    public function put(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('PUT', $uri, $options);
    }

    /**
     * Send DELETE request
     */
    public function delete(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('DELETE', $uri, $options);
    }

    /**
     * Send HEAD request
     */
    public function head(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('HEAD', $uri, $options);
    }

    /**
     * Send HTTP request with error handling
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        try {
            $this->logger->debug("Docker API Request: {$method} {$uri}", [
                'options' => $options
            ]);

            $response = $this->client->request($method, $uri, $options);

            $this->logger->debug("Docker API Response: {$response->getStatusCode()}", [
                'headers' => $response->getHeaders()
            ]);

            return $response;

        } catch (GuzzleClientException $e) {
            $this->logger->error("Docker API Client Error: {$e->getMessage()}", [
                'method' => $method,
                'uri' => $uri,
                'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);

            throw new ClientException(
                $this->extractErrorMessage($e),
                $e->getCode(),
                $e
            );

        } catch (GuzzleServerException $e) {
            $this->logger->error("Docker API Server Error: {$e->getMessage()}", [
                'method' => $method,
                'uri' => $uri,
                'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);

            throw new ServerException(
                $this->extractErrorMessage($e),
                $e->getCode(),
                $e
            );

        } catch (GuzzleException $e) {
            $this->logger->error("Docker API Request Error: {$e->getMessage()}", [
                'method' => $method,
                'uri' => $uri
            ]);

            throw new DockerException(
                "Request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Extract error message from Guzzle exception
     */
    private function extractErrorMessage(GuzzleException $e): string
    {
        if (!$e->hasResponse()) {
            return $e->getMessage();
        }

        $response = $e->getResponse();
        $body = $response->getBody()->getContents();

        // Try to decode JSON error response
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['message'])) {
            return $decoded['message'];
        }

        return $body ?: $e->getMessage();
    }

    /**
     * Get response body as JSON array
     */
    public function getJsonResponse(ResponseInterface $response): array
    {
        $body = $response->getBody()->getContents();
        
        if (empty($body)) {
            return [];
        }

        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new DockerException('Invalid JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Get response body as string
     */
    public function getStringResponse(ResponseInterface $response): string
    {
        return $response->getBody()->getContents();
    }

    /**
     * Get response body as stream
     */
    public function getStreamResponse(ResponseInterface $response): \Psr\Http\Message\StreamInterface
    {
        return $response->getBody();
    }
}