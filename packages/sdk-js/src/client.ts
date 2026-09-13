import { OndariError } from "./errors";
import { idempotencyKey } from "./idempotency";
import type { OndariOptions, TrackResult, UsageEvent } from "./types";

const DEFAULT_BASE_URL = "https://ondari.dev";

interface IngestResponse {
  ingested?: number;
  results?: TrackResult[];
  error?: string;
}

export class Ondari {
  private readonly apiKey: string;
  private readonly baseUrl: string;
  private readonly retries: number;
  private readonly timeoutMs: number;

  constructor(options: OndariOptions) {
    if (!options.apiKey) {
      throw new OndariError("apiKey is required");
    }
    this.apiKey = options.apiKey;
    this.baseUrl = (options.baseUrl ?? DEFAULT_BASE_URL).replace(/\/+$/, "");
    this.retries = options.retries ?? 3;
    this.timeoutMs = options.timeoutMs ?? 10_000;
  }

  /** Send one event and return its pricing result. */
  async track(event: UsageEvent): Promise<TrackResult> {
    const results = await this.ingest([event]);
    const first = results[0];
    if (!first) {
      throw new OndariError("empty ingest response");
    }
    if (first.error) {
      throw new OndariError(first.error);
    }
    return first;
  }

  /** Send many events in one request. Results match input order. */
  async trackBatch(events: UsageEvent[]): Promise<TrackResult[]> {
    if (events.length === 0) return [];
    return this.ingest(events);
  }

  private async ingest(events: UsageEvent[]): Promise<TrackResult[]> {
    const body = events.map((e) => this.serialize(e));

    for (let attempt = 0; ; attempt++) {
      let response: Response;
      try {
        response = await this.request(body);
      } catch (err) {
        // Network / abort errors — retry, then rethrow.
        if (attempt >= this.retries) throw err;
        await this.backoff(attempt);
        continue;
      }

      if (response.status === 429 || response.status >= 500) {
        if (attempt >= this.retries) {
          throw new OndariError(`HTTP ${response.status} after ${this.retries} retries`);
        }
        await this.backoff(attempt);
        continue;
      }

      let data: IngestResponse;
      try {
        data = (await response.json()) as IngestResponse;
      } catch {
        throw new OndariError(`invalid JSON in ${response.status} response`);
      }

      if (!response.ok) {
        throw new OndariError(data.error ?? `HTTP ${response.status}`);
      }

      return data.results ?? [];
    }
  }

  private serialize(event: UsageEvent): Record<string, unknown> {
    return {
      idempotency_key: event.idempotencyKey ?? idempotencyKey(),
      provider: event.provider,
      model: event.model,
      inputTokens: event.inputTokens,
      outputTokens: event.outputTokens,
      cachedInputTokens: event.cachedInputTokens,
      reasoningTokens: event.reasoningTokens,
      timestamp:
        event.timestamp instanceof Date
          ? event.timestamp.toISOString()
          : event.timestamp,
      environment: event.environment,
      agentId: event.agentId,
      workflowId: event.workflowId,
      taskId: event.taskId,
      latencyMs: event.latencyMs,
      status: event.status,
      source: event.source ?? "sdk-js",
      customMetadata: event.customMetadata,
    };
  }

  private async request(body: Record<string, unknown>[]): Promise<Response> {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), this.timeoutMs);
    try {
      return await fetch(`${this.baseUrl}/api/ingest`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${this.apiKey}`,
        },
        body: JSON.stringify(body),
        signal: controller.signal,
      });
    } finally {
      clearTimeout(timeout);
    }
  }

  private async backoff(attempt: number): Promise<void> {
    const delay = Math.min(1000, 100 * 2 ** attempt);
    await new Promise((resolve) => setTimeout(resolve, delay));
  }
}
