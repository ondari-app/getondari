<?php

declare(strict_types=1);

/*
 * Self-contained test runner (no external test framework).
 *
 * Run:  php tests/OndariTest.php
 * Exits non-zero on the first failure.
 */

require __DIR__ . '/../src/OndariException.php';
require __DIR__ . '/../src/HttpClient.php';
require __DIR__ . '/../src/Idempotency.php';
require __DIR__ . '/../src/Ondari.php';

use Ondari\Ondari;
use Ondari\OndariException;
use Ondari\HttpClient;
use Ondari\HttpResponse;
use Ondari\Idempotency;

final class FakeHttpClient implements HttpClient
{
    /** @var array<int, array{url:string, headers:array<string,string>, body:string}> */
    public array $calls = [];

    /** @var list<HttpResponse> */
    public array $responses = [];

    public function post(string $url, array $headers, string $body): HttpResponse
    {
        $this->calls[] = ['url' => $url, 'headers' => $headers, 'body' => $body];
        $next = array_shift($this->responses);
        return $next ?? new HttpResponse(500, '{}');
    }
}

$failures = 0;

function check(string $label, bool $condition): void
{
    global $failures;
    if ($condition) {
        echo "  ok  {$label}\n";
    } else {
        echo "FAIL  {$label}\n";
        $failures++;
    }
}

function same($expected, $actual, string $label): void
{
    check($label, $expected === $actual);
}

function okJson(array $results): HttpResponse
{
    return new HttpResponse(200, json_encode(['results' => $results], JSON_THROW_ON_ERROR));
}

// 1. track posts one event and returns the result
$fake = new FakeHttpClient();
$ab = new Ondari('k', 'https://ondari.dev', 3, $fake);
$fake->responses[] = okJson([
    ['accepted' => true, 'pricingStatus' => 'priced', 'cost' => 3.42, 'explanation' => '…'],
]);
$result = $ab->track(['provider' => 'openai', 'model' => 'gpt-4o', 'inputTokens' => 1200000, 'outputTokens' => 42000]);
same(3.42, $result['cost'], 'track returns cost');
same('priced', $result['pricingStatus'], 'track returns pricingStatus');
same('https://ondari.dev/api/ingest', $fake->calls[0]['url'], 'posts to /api/ingest');
same('Bearer k', $fake->calls[0]['headers']['Authorization'], 'sends bearer auth');
$sent = json_decode($fake->calls[0]['body'], true);
same('openai', $sent[0]['provider'], 'serializes provider');
same(1200000, $sent[0]['inputTokens'], 'serializes inputTokens');
same('sdk-php', $sent[0]['source'], 'defaults source to sdk-php');
check('generates idempotency_key', isset($sent[0]['idempotency_key']) && $sent[0]['idempotency_key'] !== '');

// 2. caller-supplied idempotency key
$fake = new FakeHttpClient();
$ab = new Ondari('k', 'https://ondari.dev', 3, $fake);
$fake->responses[] = okJson([['accepted' => true]]);
$ab->track(['provider' => 'x', 'idempotencyKey' => 'op-1']);
$sent = json_decode($fake->calls[0]['body'], true);
same('op-1', $sent[0]['idempotency_key'], 'uses caller idempotency key');

// 3. custom baseUrl
$fake = new FakeHttpClient();
$ab = new Ondari('k', 'https://selfhost.example/', 3, $fake);
$fake->responses[] = okJson([['accepted' => true]]);
$ab->track(['provider' => 'x']);
same('https://selfhost.example/api/ingest', $fake->calls[0]['url'], 'honors custom baseUrl');

// 4. throws on 401 with server message
$fake = new FakeHttpClient();
$ab = new Ondari('bad', 'https://ondari.dev', 3, $fake);
$fake->responses[] = new HttpResponse(401, json_encode(['error' => 'Unauthorized'], JSON_THROW_ON_ERROR));
try {
    $ab->track(['provider' => 'x']);
    check('throws OndariException on 401', false);
} catch (OndariException $e) {
    same('Unauthorized', $e->getMessage(), 'throws OndariException with server message');
}

// 5. retries on 429 then succeeds
$fake = new FakeHttpClient();
$ab = new Ondari('k', 'https://ondari.dev', 2, $fake);
$fake->responses[] = new HttpResponse(429, json_encode(['error' => 'Rate limit exceeded'], JSON_THROW_ON_ERROR));
$fake->responses[] = okJson([['accepted' => true]]);
$result = $ab->track(['provider' => 'x']);
same(true, $result['accepted'], 'succeeds after 429 retry');
same(2, count($fake->calls), 'retries then succeeds');

// 6. batch preserves order
$fake = new FakeHttpClient();
$ab = new Ondari('k', 'https://ondari.dev', 3, $fake);
$fake->responses[] = okJson([
    ['accepted' => true, 'eventId' => 'a'],
    ['accepted' => true, 'eventId' => 'b'],
]);
$results = $ab->trackBatch([
    ['provider' => 'openai', 'model' => 'gpt-4o'],
    ['provider' => 'anthropic', 'model' => 'claude-sonnet-4'],
]);
same(['a', 'b'], [$results[0]['eventId'], $results[1]['eventId']], 'batch preserves order');
$sent = json_decode($fake->calls[0]['body'], true);
same(2, count($sent), 'batch sends both events');

// 7. empty batch makes no request
$fake = new FakeHttpClient();
$ab = new Ondari('k', 'https://ondari.dev', 3, $fake);
$results = $ab->trackBatch([]);
same([], $results, 'empty batch returns []');
same(0, count($fake->calls), 'empty batch does not call API');

// 8. apiKey required
try {
    new Ondari('');
    check('requires apiKey', false);
} catch (OndariException $e) {
    check('requires apiKey', true);
}

// 9. idempotency keys are unique
check('idempotency keys are unique', Idempotency::key() !== Idempotency::key());

echo "\n" . ($failures === 0 ? "ALL PASSED" : "{$failures} FAILURE(S)") . "\n";
exit($failures === 0 ? 0 : 1);
