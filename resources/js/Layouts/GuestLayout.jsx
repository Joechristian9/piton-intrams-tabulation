import { Link } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";
import PitonBackdrop from "@/Components/PitonBackdrop";

const focusRing =
    "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-300 focus-visible:ring-offset-2 focus-visible:ring-offset-black";

// Dark PITON shell for the login and other account pages. The `dark` class
// switches the shared form components (TextInput, InputLabel...) to dark styles.
export default function GuestLayout({ children }) {
    return (
        <div className="dark relative flex min-h-screen flex-col overflow-x-hidden bg-black font-sans text-white selection:bg-yellow-400 selection:text-black">
            <PitonBackdrop />

            <main className="relative z-10 flex flex-1 flex-col items-center justify-center px-4 py-10 sm:px-6">
                <Link
                    href="/"
                    aria-label="PITON home"
                    className={`rounded-full ${focusRing}`}
                >
                    <picture>
                        <source srcSet="/piton-logo.webp" type="image/webp" />
                        <img
                            src="/PITON%20LOGO.png"
                            alt=""
                            width={80}
                            height={80}
                            className="h-20 w-20 object-contain [filter:drop-shadow(0_0_10px_rgba(250,204,21,0.45))]"
                        />
                    </picture>
                </Link>

                <div className="mt-6 w-full max-w-md rounded-2xl border border-white/10 bg-neutral-900/80 p-6 shadow-2xl shadow-black/60 backdrop-blur-md sm:p-8">
                    {children}
                </div>

                <Link
                    href="/"
                    className={`mt-6 inline-flex min-h-11 items-center gap-2 rounded-md px-3 text-sm text-gray-400 transition-colors duration-200 hover:text-white ${focusRing}`}
                >
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Back to home
                </Link>
            </main>

            <footer className="relative z-10 pb-6 text-center text-sm text-gray-400">
                &copy; {new Date().getFullYear()} joe-dev
            </footer>
        </div>
    );
}
