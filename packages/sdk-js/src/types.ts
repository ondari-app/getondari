/**
 * A usage event, matching the canonical Ondari event spec
 * (docs/events.md). Only `provider` is required — everything else is
 * optional and defaults server-side.
 */
export interface UsageEvent {
  /**
   * Unique per logical operation. Reusing it returns the original event,
   * so retries never double-count. Omit it and the SDK generates one —
   * but for retry-safe code, pass your own.
   */
  idempotencyKey?: string;
  timestamp?: string | Date;
  provider: string;
  model?: string;
  inputTokens?: number;
  outputTokens?: number;
  cachedInputTokens?: number;
  reasoningTokens?: number;
  environment?: string;
  agentId?: string;
  workflowId?: string;
  taskId?: string;
  latencyMs?: number;
  status?: string;
  source?: string;
  customMetadata?: Record<string, unknown>;
}

export type PricingStatus = "priced" | "unpriced";

export interface TrackResult {
  accepted: boolean;
  duplicate?: boolean;
  eventId?: string;
  pricingStatus?: PricingStatus;
  cost?: number | null;
  explanation?: string | null;
  error?: string;
}

export interface OndariOptions {
  apiKey: string;
  /** Defaults to https://ondari.dev. Point at a self-hosted instance to use it there. */
  baseUrl?: string;
  /** Retry count for network errors and 429/5xx. Default 3. */
  retries?: number;
  /** Request timeout in ms. Default 10_000. */
  timeoutMs?: number;
}
