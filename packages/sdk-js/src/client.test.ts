import { afterEach, describe, expect, it, vi } from "vitest";
import { Ondari, OndariError } from "./index";

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

function captureFetch(): { calls: { url: string; init: RequestInit }[]; respond: (r: Response) => void } {
  const calls: { url: string; init: RequestInit }[] = [];
  let responder: (r: Response) => void = () => {};
  const fn = vi.fn(async (url: string, init: RequestInit) => {
    calls.push({ url, init });
    return new Promise<Response>((resolve) => {
      responder = resolve;
    });
  });
  vi.stubGlobal("fetch", fn);
  return {
    calls,
    respond: (r: Response) => responder(r),
  };
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("Ondari.track", () => {
  it("posts one event and returns the result", async () => {
    const { calls, respond } = captureFetch();
    const ab = new Ondari({ apiKey: "k" });
    const p = ab.track({ provider: "openai", model: "gpt-4o", inputTokens: 1200000, outputTokens: 42000 });
    respond(jsonResponse({ ingested: 1, results: [{ accepted: true, pricingStatus: "priced", cost: 3.42, explanation: "…" }] }));

    const result = await p;
    expect(result.cost).toBe(3.42);
    expect(result.pricingStatus).toBe("priced");

    const { url, init } = calls[0];
    expect(url).toBe("https://ondari.dev/api/ingest");
    expect(init.method).toBe("POST");
    expect(init.headers).toMatchObject({ Authorization: "Bearer k" });

    const body = JSON.parse(init.body as string);
    expect(body).toHaveLength(1);
    expect(body[0]).toMatchObject({ provider: "openai", model: "gpt-4o", inputTokens: 1200000, outputTokens: 42000 });
    expect(body[0].idempotency_key).toBeTruthy();
    expect(body[0].source).toBe("sdk-js");
  });

  it("uses a caller-supplied idempotencyKey", async () => {
    const { calls, respond } = captureFetch();
    const ab = new Ondari({ apiKey: "k" });
    const p = ab.track({ provider: "x", idempotencyKey: "op-1" });
    respond(jsonResponse({ results: [{ accepted: true }] }));
    await p;

    const body = JSON.parse(calls[0].init.body as string);
    expect(body[0].idempotency_key).toBe("op-1");
  });

  it("accepts a Date timestamp and serializes to ISO", async () => {
    const { calls, respond } = captureFetch();
    const ab = new Ondari({ apiKey: "k" });
    const p = ab.track({ provider: "x", timestamp: new Date("2026-09-09T14:00:00.000Z") });
    respond(jsonResponse({ results: [{ accepted: true }] }));
    await p;

    const body = JSON.parse(calls[0].init.body as string);
    expect(body[0].timestamp).toBe("2026-09-09T14:00:00.000Z");
  });

  it("honors a custom baseUrl", async () => {
    const { calls, respond } = captureFetch();
    const ab = new Ondari({ apiKey: "k", baseUrl: "https://selfhost.example/" });
    const p = ab.track({ provider: "x" });
    respond(jsonResponse({ results: [{ accepted: true }] }));
    await p;
    expect(calls[0].url).toBe("https://selfhost.example/api/ingest");
  });

  it("throws OndariError on 401 with the server message", async () => {
    const { respond } = captureFetch();
    const ab = new Ondari({ apiKey: "bad" });
    const p = ab.track({ provider: "x" });
    respond(jsonResponse({ error: "Unauthorized" }, 401));
    await expect(p).rejects.toThrow(OndariError);
    await expect(p).rejects.toThrow("Unauthorized");
  });

  it("retries on 429 then succeeds", async () => {
    const { calls, respond } = captureFetch();
    const ab = new Ondari({ apiKey: "k", retries: 2 });
    const p = ab.track({ provider: "x" });

    // first attempt -> 429
    respond(jsonResponse({ error: "Rate limit exceeded" }, 429));
    await vi.waitFor(() => expect(calls.length).toBe(2));
    respond(jsonResponse({ results: [{ accepted: true }] }));

    const result = await p;
    expect(result.accepted).toBe(true);
    expect(calls.length).toBe(2);
  });
});

describe("Ondari.trackBatch", () => {
  it("sends all events in one request and preserves order", async () => {
    const { calls, respond } = captureFetch();
    const ab = new Ondari({ apiKey: "k" });
    const p = ab.trackBatch([
      { provider: "openai", model: "gpt-4o" },
      { provider: "anthropic", model: "claude-sonnet-4" },
    ]);
    respond(jsonResponse({
      results: [
        { accepted: true, eventId: "a" },
        { accepted: true, eventId: "b" },
      ],
    }));

    const results = await p;
    expect(results.map((r) => r.eventId)).toEqual(["a", "b"]);

    const body = JSON.parse(calls[0].init.body as string);
    expect(body).toHaveLength(2);
  });

  it("returns [] for an empty batch without calling the API", async () => {
    const { calls } = captureFetch();
    const ab = new Ondari({ apiKey: "k" });
    await expect(ab.trackBatch([])).resolves.toEqual([]);
    expect(calls).toHaveLength(0);
  });
});
