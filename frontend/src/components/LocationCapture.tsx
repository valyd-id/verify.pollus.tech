import { useState } from "react";
import { motion } from "framer-motion";
import { MapPin, Loader2, CheckCircle2 } from "lucide-react";

export type LocationBody =
  | { latitude: number; longitude: number; accuracy?: number }
  | { denied: true };

type Props = {
  /** Called with the captured coordinates, or { denied: true } if skipped/blocked. */
  onCapture: (body: LocationBody) => void;
};

/**
 * Captures the device's GPS position via navigator.geolocation. Capture-only:
 * a successful fix is recorded; if the user blocks or skips, we report
 * { denied: true } so the server records the step for review rather than failing.
 * Geolocation requires a secure context (https) and, in the embedded modal, the
 * `geolocation` permission on the iframe `allow` attribute.
 */
export function LocationCapture({ onCapture }: Props) {
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const share = () => {
    setError(null);
    if (!navigator.geolocation) {
      setError("Location isn't available on this device. You can skip this step.");
      return;
    }
    setBusy(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setBusy(false);
        setDone(true);
        onCapture({
          latitude: pos.coords.latitude,
          longitude: pos.coords.longitude,
          accuracy: Number.isFinite(pos.coords.accuracy) ? pos.coords.accuracy : undefined,
        });
      },
      (err) => {
        setBusy(false);
        setError(
          err.code === err.PERMISSION_DENIED
            ? "Location permission was blocked. Allow it, or skip this step."
            : "Couldn't get your location. Try again, or skip this step.",
        );
      },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
    );
  };

  return (
    <div className="w-full text-center">
      <div className="mx-auto h-14 w-14 rounded-2xl bg-primary-soft border border-border flex items-center justify-center text-primary mb-4">
        {done ? <CheckCircle2 className="h-7 w-7" /> : <MapPin className="h-7 w-7" strokeWidth={1.75} />}
      </div>
      <h3 className="font-display text-2xl text-foreground">Share your location</h3>
      <p className="mt-1 text-sm text-muted-foreground">
        We use your device location only for this verification. Your browser will ask for permission.
      </p>

      {error && <p className="mt-4 text-sm text-red-600">{error}</p>}

      <div className="mt-6 flex flex-col items-center gap-3">
        <motion.button
          whileTap={{ scale: 0.96 }}
          onClick={share}
          disabled={busy || done}
          className="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-medium text-primary-foreground hover:opacity-90 transition-opacity disabled:opacity-60"
        >
          {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <MapPin className="h-4 w-4" />}
          {busy ? "Getting your location…" : "Share my location"}
        </motion.button>
        <button
          onClick={() => onCapture({ denied: true })}
          disabled={busy || done}
          className="text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground disabled:opacity-60"
        >
          Skip this step
        </button>
      </div>
    </div>
  );
}
