// Embedded-mode messaging for the hosted flow. When the SPA is opened inside a
// popup window or a modal iframe by the browser SDK (valyd-verify-js), it reports
// lifecycle/result events to the opener/parent via postMessage instead of doing a
// full-page redirect. The SDK launches us with ?display=popup|modal&origin=<host>.

export type EmbedMessage =
  | { type: "ready" }
  | { type: "complete"; sessionId: string; status: string }
  | { type: "close" }
  | { type: "error"; message: string };

export interface EmbedContext {
  embedded: boolean;
  mode: "modal" | "popup" | null;
  target: Window | null;
  targetOrigin: string;
}

let cached: EmbedContext | null = null;

export function getEmbedContext(): EmbedContext {
  if (cached) return cached;

  const params = new URLSearchParams(window.location.search);
  const display = params.get("display");
  const origin = params.get("origin");
  const mode = display === "popup" ? "popup" : display === "modal" ? "modal" : null;

  // Resolve the window we should talk to. Popups use window.opener; modal iframes
  // use window.parent. Auto-detect even if the display param is absent.
  let target: Window | null = null;
  if (mode === "popup") target = window.opener ?? null;
  else if (mode === "modal") target = window.parent !== window ? window.parent : null;
  else if (window.opener) target = window.opener as Window;
  else if (window.parent !== window) target = window.parent;

  const embedded = Boolean(mode) || Boolean(target);
  const targetOrigin = origin && /^https?:\/\//i.test(origin) ? origin : "*";

  cached = { embedded, mode, target, targetOrigin };
  return cached;
}

/** Post an event to the embedding page (no-op when not embedded). */
export function postToParent(msg: EmbedMessage): void {
  const ctx = getEmbedContext();
  if (!ctx.embedded || !ctx.target) return;
  try {
    ctx.target.postMessage({ source: "valyd-verify", ...msg }, ctx.targetOrigin);
  } catch {
    /* ignore cross-origin/postMessage failures */
  }
}
