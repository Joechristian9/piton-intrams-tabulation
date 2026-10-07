import { Head } from "@inertiajs/react";
import PageLayout from "@/Layouts/PageLayout";
import PitonBackdrop from "@/Components/PitonBackdrop";
import PitonHero from "@/Components/PitonHero";
import DeveloperCredit from "@/Components/DeveloperCredit";

// The signed-in home: the PITON landing hero (same look and animation as the public
// landing page) inside the app, opened by clicking the logo header in the sidebar.
// Just the hero, no button: the sidebar is right there for getting around.
// Loads the animation library, but only when this page is opened (its own chunk).
export default function Home() {
    return (
        <>
            <Head title="Home" />
            <div className="relative flex min-h-full flex-1 flex-col overflow-hidden bg-black text-center text-white selection:bg-yellow-400 selection:text-black">
                <PitonBackdrop contained />
                <div className="relative z-10 flex flex-1 flex-col items-center justify-center px-6 py-16">
                    <PitonHero />
                </div>
                <DeveloperCredit />
            </div>
        </>
    );
}

// Persistent layout: the sidebar and live-update pollers stay mounted between pages
// (no remount, no extra poll request per click).
Home.layout = (page) => <PageLayout>{page}</PageLayout>;
