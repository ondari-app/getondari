<?php

declare(strict_types=1);

namespace Ondari;

/** A minimal HTTP response value object. */
final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {
    }
}

/** Transport seam — injected in tests, curl-backed in production. */
interface HttpClient
{
    public function post(string $url, array $headers, string $body): HttpResponse;
}

final class CurlHttpClient implements HttpClient
{
    public function __construct(private readonly int $timeoutMs = 10_000)
    {
    }

    public function post(string $url, array $headers, string $body): HttpResponse
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new OndariException('curl_init failed');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $this->timeoutMs,
        ]);

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new OndariException("curl error: {$error}");
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return new HttpResponse($status, $responseBody);
    }
}
