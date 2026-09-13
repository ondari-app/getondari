import json
import unittest

from ondari import Ondari, OndariError, idempotency_key
from ondari.http import HttpResponse


class FakeHttpClient:
    def __init__(self):
        self.calls = []
        self.responses = []

    def post(self, url, headers, body):
        self.calls.append({"url": url, "headers": headers, "body": body})
        if self.responses:
            return self.responses.pop(0)
        return HttpResponse(500, "{}")


def ok_json(results):
    return HttpResponse(200, json.dumps({"results": results}))


class TestTrack(unittest.TestCase):
    def test_track_posts_one_event_and_returns_result(self):
        fake = FakeHttpClient()
        fake.responses.append(ok_json([
            {"accepted": True, "pricingStatus": "priced", "cost": 3.42, "explanation": "..."},
        ]))
        ab = Ondari("k", http=fake)
        result = ab.track({"provider": "openai", "model": "gpt-4o", "inputTokens": 1200000, "outputTokens": 42000})

        self.assertEqual(result["cost"], 3.42)
        self.assertEqual(result["pricingStatus"], "priced")

        call = fake.calls[0]
        self.assertEqual(call["url"], "https://ondari.dev/api/ingest")
        self.assertEqual(call["headers"]["Authorization"], "Bearer k")
        sent = json.loads(call["body"])
        self.assertEqual(len(sent), 1)
        self.assertEqual(sent[0]["provider"], "openai")
        self.assertEqual(sent[0]["inputTokens"], 1200000)
        self.assertEqual(sent[0]["source"], "sdk-python")
        self.assertTrue(sent[0]["idempotency_key"])

    def test_uses_caller_idempotency_key(self):
        fake = FakeHttpClient()
        fake.responses.append(ok_json([{"accepted": True}]))
        ab = Ondari("k", http=fake)
        ab.track({"provider": "x", "idempotencyKey": "op-1"})
        sent = json.loads(fake.calls[0]["body"])
        self.assertEqual(sent[0]["idempotency_key"], "op-1")

    def test_honors_custom_base_url(self):
        fake = FakeHttpClient()
        fake.responses.append(ok_json([{"accepted": True}]))
        ab = Ondari("k", base_url="https://selfhost.example/", http=fake)
        ab.track({"provider": "x"})
        self.assertEqual(fake.calls[0]["url"], "https://selfhost.example/api/ingest")

    def test_serializes_datetime_timestamp(self):
        fake = FakeHttpClient()
        fake.responses.append(ok_json([{"accepted": True}]))
        ab = Ondari("k", http=fake)
        import datetime
        ab.track({"provider": "x", "timestamp": datetime.datetime(2026, 9, 9, 14, 0, 0)})
        sent = json.loads(fake.calls[0]["body"])
        self.assertEqual(sent[0]["timestamp"], "2026-09-09T14:00:00")

    def test_throws_on_401_with_server_message(self):
        fake = FakeHttpClient()
        fake.responses.append(HttpResponse(401, json.dumps({"error": "Unauthorized"})))
        ab = Ondari("bad", http=fake)
        with self.assertRaises(OndariError) as ctx:
            ab.track({"provider": "x"})
        self.assertIn("Unauthorized", str(ctx.exception))

    def test_retries_on_429_then_succeeds(self):
        fake = FakeHttpClient()
        fake.responses.append(HttpResponse(429, json.dumps({"error": "Rate limit exceeded"})))
        fake.responses.append(ok_json([{"accepted": True}]))
        ab = Ondari("k", retries=2, http=fake)
        result = ab.track({"provider": "x"})
        self.assertTrue(result["accepted"])
        self.assertEqual(len(fake.calls), 2)

    def test_requires_api_key(self):
        with self.assertRaises(OndariError):
            Ondari("")


class TestTrackBatch(unittest.TestCase):
    def test_batch_preserves_order(self):
        fake = FakeHttpClient()
        fake.responses.append(ok_json([
            {"accepted": True, "eventId": "a"},
            {"accepted": True, "eventId": "b"},
        ]))
        ab = Ondari("k", http=fake)
        results = ab.track_batch([
            {"provider": "openai", "model": "gpt-4o"},
            {"provider": "anthropic", "model": "claude-sonnet-4"},
        ])
        self.assertEqual([r["eventId"] for r in results], ["a", "b"])
        sent = json.loads(fake.calls[0]["body"])
        self.assertEqual(len(sent), 2)

    def test_empty_batch_makes_no_request(self):
        fake = FakeHttpClient()
        ab = Ondari("k", http=fake)
        self.assertEqual(ab.track_batch([]), [])
        self.assertEqual(len(fake.calls), 0)


class TestIdempotency(unittest.TestCase):
    def test_unique(self):
        self.assertNotEqual(idempotency_key(), idempotency_key())


if __name__ == "__main__":
    unittest.main()
