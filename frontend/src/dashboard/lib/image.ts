// Read an image File and return a compact base64 data URL. The image is
// downscaled to `maxDim` (longest edge) and re-encoded so logos stay well
// under the backend's size limit regardless of the original file.
export async function fileToLogoDataUrl(file: File, maxDim = 256, quality = 0.9): Promise<string> {
  const dataUrl = await new Promise<string>((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result));
    reader.onerror = () => reject(reader.error);
    reader.readAsDataURL(file);
  });

  const img = await new Promise<HTMLImageElement>((resolve, reject) => {
    const el = new Image();
    el.onload = () => resolve(el);
    el.onerror = () => reject(new Error("Could not read image"));
    el.src = dataUrl;
  });

  const scale = Math.min(1, maxDim / Math.max(img.width, img.height));
  const w = Math.max(1, Math.round(img.width * scale));
  const h = Math.max(1, Math.round(img.height * scale));

  const canvas = document.createElement("canvas");
  canvas.width = w;
  canvas.height = h;
  const ctx = canvas.getContext("2d");
  if (!ctx) return dataUrl;
  ctx.drawImage(img, 0, 0, w, h);

  // PNG preserves transparency; fall back to the original if it somehow grows.
  const out = canvas.toDataURL("image/png", quality);
  return out.length < dataUrl.length ? out : dataUrl;
}
