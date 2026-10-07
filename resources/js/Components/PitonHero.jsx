import { motion, useReducedMotion } from "motion/react";
import "@fontsource/orbitron/700.css";
import "@fontsource/orbitron/900.css";

// The PITON hero shared by the public landing page (Welcome) and the signed-in home
// (Home): glowing logo with slow orbit rings, the wordmark, the organization and the
// tagline, entering in a stagger. `children` is the call to action below it. Skips
// the entrance animation (and the rings spin) for people who prefer reduced motion.

// Orbitron echoes the techno lettering in the PITON logo; used for the wordmark only.
const displayFont = "font-['Orbitron',ui-sans-serif,system-ui,sans-serif]";

// Small HUD-style corner brackets framing the logo.
const CORNERS = [
    "left-0 top-0 border-l-2 border-t-2",
    "right-0 top-0 border-r-2 border-t-2",
    "bottom-0 left-0 border-b-2 border-l-2",
    "bottom-0 right-0 border-b-2 border-r-2",
];

export const heroFocusRing =
    "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-300 focus-visible:ring-offset-2 focus-visible:ring-offset-black";

export default function PitonHero({ children }) {
    const reduceMotion = useReducedMotion();

    // Staggered entrance; skipped entirely when the user prefers reduced motion.
    const item = (delay) =>
        reduceMotion
            ? {}
            : {
                  initial: { opacity: 0, y: 16 },
                  animate: { opacity: 1, y: 0 },
                  transition: { duration: 0.5, delay, ease: "easeOut" },
              };

    return (
        <>
            {/* Logo with glow, slow orbit rings and corner brackets */}
            <motion.div
                className="relative grid h-48 w-48 place-items-center sm:h-60 sm:w-60"
                {...(reduceMotion
                    ? {}
                    : {
                          initial: { opacity: 0, scale: 0.92 },
                          animate: { opacity: 1, scale: 1 },
                          transition: { duration: 0.6, ease: "easeOut" },
                      })}
            >
                <div className="absolute inset-6 rounded-full bg-yellow-400/15 blur-3xl" aria-hidden="true" />
                <div
                    className="absolute inset-2 rounded-full border border-dashed border-yellow-400/30 motion-safe:animate-[spin_40s_linear_infinite]"
                    aria-hidden="true"
                />
                <div
                    className="absolute inset-6 rounded-full border border-blue-400/25 motion-safe:animate-[spin_60s_linear_infinite_reverse]"
                    aria-hidden="true"
                />
                {CORNERS.map((pos) => (
                    <span key={pos} className={`absolute h-5 w-5 border-yellow-400/70 ${pos}`} aria-hidden="true" />
                ))}

                <picture className="relative">
                    <source srcSet="/piton-logo.webp" type="image/webp" />
                    <img
                        src="/PITON%20LOGO.png"
                        alt="PITON shield logo"
                        width={176}
                        height={176}
                        decoding="async"
                        className="h-32 w-32 object-contain sm:h-40 sm:w-40 [filter:drop-shadow(0_0_10px_rgba(250,204,21,0.45))]"
                    />
                </picture>
            </motion.div>

            <motion.p {...item(0.1)} className="mt-8 font-mono text-xs uppercase tracking-[0.35em] text-blue-300">
                Est. 2007
            </motion.p>

            <motion.h1 {...item(0.18)} className="mt-3">
                <span
                    className={`block bg-gradient-to-b from-yellow-100 via-yellow-400 to-amber-500 bg-clip-text text-5xl font-black tracking-[0.08em] text-transparent sm:text-7xl lg:text-8xl ${displayFont} [filter:drop-shadow(0_0_24px_rgba(250,204,21,0.25))]`}
                >
                    PITON
                </span>
                <span className="mt-3 block text-2xl font-semibold tracking-tight text-white sm:text-4xl">
                    Tabulation System
                </span>
            </motion.h1>

            <motion.div
                {...item(0.26)}
                className="mt-6 h-px w-40 bg-gradient-to-r from-transparent via-yellow-400/70 to-transparent"
                aria-hidden="true"
            />

            <motion.div {...item(0.32)}>
                <p className="mt-6 text-base text-gray-300 [text-wrap:balance] sm:text-lg">
                    Philippine Information Technology of the North
                </p>
                <p className="mt-2 font-mono text-sm uppercase tracking-[0.25em] text-gray-400">Coding Our Future</p>
            </motion.div>

            {children && (
                <motion.div {...item(0.4)} className="mt-10 flex flex-col items-center">
                    {children}
                </motion.div>
            )}
        </>
    );
}
