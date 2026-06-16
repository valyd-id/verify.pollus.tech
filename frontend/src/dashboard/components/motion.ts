import type { Variants } from "framer-motion";

// Shared, intentionally subtle motion primitives for the console. Short
// durations + small offsets so the UI feels responsive, not animated-for-show.

const easeOut: [number, number, number, number] = [0.22, 1, 0.36, 1];

export const fadeUp: Variants = {
  hidden: { opacity: 0, y: 8 },
  show: { opacity: 1, y: 0, transition: { duration: 0.24, ease: easeOut } },
};

export const listContainer: Variants = {
  hidden: {},
  show: { transition: { staggerChildren: 0.04, delayChildren: 0.03 } },
};

export const listItem: Variants = {
  hidden: { opacity: 0, y: 6 },
  show: { opacity: 1, y: 0, transition: { duration: 0.22, ease: easeOut } },
};

export const overlay: Variants = {
  hidden: { opacity: 0 },
  show: { opacity: 1, transition: { duration: 0.15 } },
  exit: { opacity: 0, transition: { duration: 0.12 } },
};

export const dialog: Variants = {
  hidden: { opacity: 0, scale: 0.97, y: 10 },
  show: { opacity: 1, scale: 1, y: 0, transition: { duration: 0.22, ease: easeOut } },
  exit: { opacity: 0, scale: 0.98, y: 6, transition: { duration: 0.13 } },
};

export const menu: Variants = {
  hidden: { opacity: 0, scale: 0.96, y: -4 },
  show: { opacity: 1, scale: 1, y: 0, transition: { duration: 0.13, ease: easeOut } },
  exit: { opacity: 0, scale: 0.97, y: -2, transition: { duration: 0.1 } },
};
