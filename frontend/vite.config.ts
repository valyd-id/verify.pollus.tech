import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";

// The hosted verification SPA. In dev it proxies /api to the local Laravel
// backend so calls are same-origin (no CORS); in prod nginx does the same.
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    port: 5173,
    proxy: {
      "/api": {
        target: process.env.VERIFY_API_URL ?? "http://127.0.0.1:8088",
        changeOrigin: true,
      },
    },
  },
});
