import { Head, Link } from "@inertiajs/react";
import { ArrowRight, LayoutDashboard, LogIn } from "lucide-react";
import PitonBackdrop from "@/Components/PitonBackdrop";
import PitonHero, { heroFocusRing } from "@/Components/PitonHero";
import DeveloperCredit from "@/Components/DeveloperCredit";

// The public landing page. The hero (logo, wordmark, tagline, entrance animation) is
// shared with the signed-in home (Pages/Home.jsx) through Components/PitonHero.jsx.
export default function Welcome({ auth }) {
    const isLoggedIn = Boolean(auth?.user);

    const cta = isLoggedIn
        ? { href: route("dashboard"), label: "Go to dashboard", Icon: LayoutDashboard }
        : { href: route("login"), label: "Log in", Icon: LogIn };

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
                    <PitonHero>
                        <Link
                            href={cta.href}
                            className={`group inline-flex min-h-12 cursor-pointer items-center gap-2 rounded-full bg-yellow-400 px-8 text-base font-semibold text-black shadow-[0_0_24px_rgba(250,204,21,0.35)] transition duration-200 hover:bg-yellow-300 hover:shadow-[0_0_36px_rgba(250,204,21,0.55)] active:scale-[0.98] ${heroFocusRing}`}
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
                    </PitonHero>
                </main>

                <DeveloperCredit />
            </div>
        </>
    );
}
