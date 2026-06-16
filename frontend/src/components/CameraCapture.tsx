import { useCallback, useEffect, useRef, useState } from "react";
import { motion } from "framer-motion";
import { Camera, RefreshCw, Upload, Check } from "lucide-react";

type Props = {
  facingMode: "user" | "environment";
  overlay: "card" | "oval";
  title: string;
  hint: string;
  onCapture: (dataUrl: string) => void;
  onSkip?: () => void;
};

/**
 * Live camera capture with a canvas snapshot, retake step, and a file-upload
 * fallback. Camera APIs require a secure context (https or localhost).
 */
export function CameraCapture({ facingMode, overlay, title, hint, onCapture, onSkip }: Props) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const [ready, setReady] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [shot, setShot] = useState<string | null>(null);

  const stop = useCallback(() => {
    streamRef.current?.getTracks().forEach((t) => t.stop());
    streamRef.current = null;
  }, []);

  const start = useCallback(async () => {
    setError(null);
    setShot(null);
    try {
      if (!navigator.mediaDevices?.getUserMedia) throw new Error("no-api");
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode, width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false,
      });
      streamRef.current = stream;
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        await videoRef.current.play().catch(() => {});
      }
      setReady(true);
    } catch (e) {
      const name = (e as Error)?.name || "";
      setReady(false);
      setError(
        name === "NotAllowedError"
          ? "Camera permission was blocked. Allow it, or upload a photo instead."
          : "No camera available. Upload a photo instead.",
      );
    }
  }, [facingMode]);

  useEffect(() => {
    start();
    return stop;
  }, [start, stop]);

  const capture = () => {
    const v = videoRef.current;
    if (!v || !v.videoWidth) return;
    const canvas = document.createElement("canvas");
    canvas.width = v.videoWidth;
    canvas.height = v.videoHeight;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;
    if (facingMode === "user") {
      ctx.translate(canvas.width, 0);
      ctx.scale(-1, 1);
    }
    ctx.drawImage(v, 0, 0, canvas.width, canvas.height);
    setShot(canvas.toDataURL("image/jpeg", 0.92));
    stop();
  };

  const confirm = () => shot && onCapture(shot);

  const onFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => onCapture(String(reader.result));
    reader.readAsDataURL(file);
  };

  return (
    <div className="w-full">
      <div className="text-center mb-4">
        <h3 className="font-display text-2xl text-foreground">{title}</h3>
        <p className="mt-1 text-sm text-muted-foreground">{hint}</p>
      </div>

      <div className="relative mx-auto max-w-sm overflow-hidden rounded-2xl border border-border bg-foreground/5">
        {shot ? (
          <img src={shot} alt="capture preview" className={`w-full object-cover ${overlay === "oval" ? "aspect-[3/4]" : "aspect-[16/10]"}`} />
        ) : (
          <video
            ref={videoRef}
            playsInline
            muted
            className={`w-full object-cover ${overlay === "oval" ? "aspect-[3/4] scale-x-[-1]" : "aspect-[16/10]"}`}
          />
        )}

        {!shot && !error && (
          <div aria-hidden className="pointer-events-none absolute inset-0 flex items-center justify-center p-6">
            <div
              className={`w-full border-2 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)] ${
                overlay === "oval" ? "aspect-[3/4] [clip-path:ellipse(42%_46%_at_50%_50%)]" : "aspect-[16/10] rounded-2xl"
              }`}
            />
          </div>
        )}

        {error && (
          <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center">
            <p className="text-sm text-white/90 bg-foreground/60 rounded-lg px-3 py-2">{error}</p>
          </div>
        )}
      </div>

      <div className="mt-5 flex flex-col items-center gap-3">
        {shot ? (
          <div className="flex gap-3 w-full max-w-sm">
            <button
              onClick={start}
              className="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 py-3 text-sm font-medium text-foreground hover:bg-secondary transition-colors"
            >
              <RefreshCw className="h-4 w-4" /> Retake
            </button>
            <button
              onClick={confirm}
              className="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-medium text-primary-foreground hover:opacity-90 transition-opacity"
            >
              <Check className="h-4 w-4" /> Use photo
            </button>
          </div>
        ) : (
          ready && (
            <motion.button
              whileTap={{ scale: 0.94 }}
              onClick={capture}
              aria-label="Capture"
              className="h-16 w-16 rounded-full bg-primary text-primary-foreground flex items-center justify-center shadow-[var(--shadow-lift)]"
            >
              <Camera className="h-6 w-6" />
            </motion.button>
          )
        )}

        {!shot && (
          <label className="inline-flex items-center gap-2 text-sm text-muted-foreground cursor-pointer hover:text-foreground transition-colors">
            <Upload className="h-4 w-4" />
            <span>Upload a photo instead</span>
            <input
              type="file"
              accept="image/*"
              capture={facingMode === "user" ? "user" : "environment"}
              className="hidden"
              onChange={onFile}
            />
          </label>
        )}

        {onSkip && !shot && (
          <button onClick={onSkip} className="text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground">
            Skip this step
          </button>
        )}
      </div>
    </div>
  );
}
