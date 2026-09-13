<?php

declare(strict_types=1);

namespace Ondari;

/**
 * The Ondari PHP client. Send usage events and read back itemized,
 * explainable costs. Cost is server-computed — you never send a price.
 */
final class Ondari
{
    private const DEFAULT_BASE_URL = 'https://ondari.dev';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly int $retries = 3,
        private readonly ?HttpClient $http = null,
    ) {
        if ($apiKey === '') {
            throw new OndariException('apiKey is required');
        }
    }

    /**
     * Send one event and return its pricing result.
     *
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public function track(array $event): array
    {
        $results = $this->ingest([$event]);
        $first = $results[0] ?? null;
        if ($first === null) {
            throw new OndariException('empty ingest response');
        }
        if (!empty($first['error'])) {
            throw new OndariException((string) $first['error']);
        }

        return $first;
    }

    /**
     * Send many events in one request. Results match input order.
     *
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    public function trackBatch(array $events): array
    {
        if ($events === []) {
            return [];
        }

        return $this->ingest($events);
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    private function ingest(array $events): array
    {
        $body = array_map(fn (array $event): array => $this->serialize($event), $events);
        $json = json_encode($body, JSON_THROW_ON_ERROR);
        $url = rtrim($this->baseUrl, '/') . '/api/ingest';
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];
        $client = $this->http ?? new CurlHttpClient();

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $client->post($url, $headers, $json);
            } catch (OndariException $e) {
                if ($attempt >= $this->retries) {
                    throw $e;
                }
                $this->backoff($attempt);
                continue;
            }

            if ($response->status === 429 || $response->status >= 500) {
                if ($attempt >= $this->retries) {
                    throw new OndariException("HTTP {$response->status} after {$this->retries} retries");
                }
                $this->backoff($attempt);
                continue;
            }

            $data = json_decode($response->body, true);
            if (!is_array($data)) {
                throw new OndariException("invalid JSON in {$response->status} response");
            }

            if ($response->status >= 400) {
                $message = $data['error'] ?? "HTTP {$response->status}";
                throw new OndariException((string) $message);
            }

            return $data['results'] ?? [];
        }
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function serialize(array $event): array
    {
        return [
            'idempotency_key' => $event['idempotencyKey'] ?? Idempotency::key(),
            'provider' => $event['provider'] ?? null,
            'model' => $event['model'] ?? null,
            'inputTokens' => $event['inputTokens'] ?? null,
            'outputTokens' => $event['outputTokens'] ?? null,
            'cachedInputTokens' => $event['cachedInputTokens'] ?? null,
            'reasoningTokens' => $event['reasoningTokens'] ?? null,
            'timestamp' => $event['timestamp'] ?? null,
            'environment' => $event['environment'] ?? null,
            'agentId' => $event['agentId'] ?? null,
            'workflowId' => $event['workflowId'] ?? null,
            'taskId' => $event['taskId'] ?? null,
            'latencyMs' => $event['latencyMs'] ?? null,
            'status' => $event['status'] ?? null,
            'source' => $event['source'] ?? 'sdk-php',
            'customMetadata' => $event['customMetadata'] ?? null,
        ];
    }

    private function backoff(int $attempt): void
    {
        $ms = min(1000, 100 * (2 ** $attempt));
        usleep($ms * 1000);
    }
}
