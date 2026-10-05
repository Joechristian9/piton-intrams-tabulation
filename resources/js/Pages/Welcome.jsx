import { Head, Link } from "@inertiajs/react";
import { motion, useReducedMotion } from "motion/react";
import { ArrowRight, LayoutDashboard, LogIn } from "lucide-react";
import PitonBackdrop from "@/Components/PitonBackdrop";
import "@fontsource/orbitron/700.css";
import "@fontsource/orbitron/900.css";

const focusRing =
    "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-300 focus-visible:ring-offset-2 focus-visible:ring-offset-black";

// Orbitron echoes the techno lettering in the PITON logo; used for the wordmark only.
const displayFont = "font-['Orbitron',ui-sans-serif,system-ui,sans-serif]";

// Small HUD-style corner brackets framing the logo.
const CORNERS = [
    "left-0 top-0 border-l-2 border-t-2",
    "right-0 top-0 border-r-2 border-t-2",
    "bottom-0 left-0 border-b-2 border-l-2",
    "bottom-0 right-0 border-b-2 border-r-2",
];

export default function Welcome({ auth }) {
    const reduceMotion = useReducedMotion();
    const isLoggedIn = Boolean(auth?.user);

    const cta = isLoggedIn
        ? { href: route("dashboard"), label: "Go to dashboard", Icon: LayoutDashboard }
        : { href: route("login"), label: "Log in", Icon: LogIn };

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
            <Head title="Tabulation System">
                <meta
                    name="description"
                    content="PITON Tabulation System by the Philippine Information Technology of the North."
                />
                <link rel="preload" as="image" type="image/webp" href="/piton-logo.webp" />
            </Head>

            <div className="relative flex min-h-screen flex-col overflow-x-hidden bg-black font-sans text-white selection:bg-yellow-400 selection:text-black">
                <PitonBackdrop />

                <main className="relative z-10 flex flex-1 flex-col items-center justify-center px-6 py-16 text-center">
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
                        <div
                            className="absolute inset-6 rounded-full bg-yellow-400/15 blur-3xl"
                            aria-hidden="true"
                        />
                        <div
                            className="absolute inset-2 rounded-full border border-dashed border-yellow-400/30 motion-safe:animate-[spin_40s_linear_infinite]"
                            aria-hidden="true"
                        />
                        <div
                            className="absolute inset-6 rounded-full border border-blue-400/25 motion-safe:animate-[spin_60s_linear_infinite_reverse]"
                            aria-hidden="true"
                        />
                        {CORNERS.map((pos) => (
                            <span
                                key={pos}
                                className={`absolute h-5 w-5 border-yellow-400/70 ${pos}`}
                                aria-hidden="true"
                            />
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

                    <motion.p
                        {...item(0.1)}
                        className="mt-8 font-mono text-xs uppercase tracking-[0.35em] text-blue-300"
                    >
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
                        <p className="mt-2 font-mono text-sm uppercase tracking-[0.25em] text-gray-400">
                            Coding Our Future
                        </p>
                    </motion.div>

                    <motion.div {...item(0.4)} className="mt-10 flex flex-col items-center">
                        <Link
                            href={cta.href}
                            className={`group inline-flex min-h-12 cursor-pointer items-center gap-2 rounded-full bg-yellow-400 px-8 text-base font-semibold text-black shadow-[0_0_24px_rgba(250,204,21,0.35)] transition duration-200 hover:bg-yellow-300 hover:shadow-[0_0_36px_rgba(250,204,21,0.55)] active:scale-[0.98] ${focusRing}`}
                        >
                            <cta.Icon className="h-5 w-5" aria-hidden="true" />
                            {cta.label}
                            <ArrowRight
                                className="h-5 w-5 transition-transform duration-200 motion-safe:group-hover:translate-x-1"
                                aria-hidden="true"
                            />
                        </Link>
                        <p className="mt-4 text-sm text-gray-400">
                            For judges and organizers
                        </p>
                    </motion.div>
                </main>

                <footer className="relative z-10 pb-6 text-center text-sm text-gray-400">
                    &copy; {new Date().getFullYear()} joe-dev
                </footer>
            </div>
        </>
    );
}
