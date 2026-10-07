import { Head } from "@inertiajs/react";
import { Clock, Flag } from "lucide-react";
import PageLayout from "@/Layouts/PageLayout";

// Shown to a judge whose event isn't live. JudgeNotifications (in PageLayout)
// reloads the page when the event's live stamp changes, so the judge is taken to
// scoring as soon as the admin starts the event.
export default function Waiting({ state, eventName }) {
    const ended = state === "ended";
    const Icon = ended ? Flag : Clock;

    return (
        <>
            <Head title={eventName} />
            <div className="flex min-h-[70vh] items-center justify-center px-4">
                <div
                    role="status"
                    className="w-full max-w-md rounded-2xl border border-yellow-400/40 bg-neutral-900 p-8 text-center text-white"
                >
                    <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-yellow-400/15 text-yellow-400">
                        <Icon className="h-7 w-7" aria-hidden="true" />
                    </span>
                    <p className="mt-4 text-sm font-medium uppercase tracking-wider text-yellow-300">
                        {eventName}
                    </p>
                    <h1 className="mt-2 text-xl font-bold">
                        {ended ? "This event has ended" : "Your event hasn't started yet"}
                    </h1>
                    <p className="mt-2 text-sm text-gray-300">
                        {ended
                            ? "Scoring is closed. Thank you for judging!"
                            : "This page opens the scoring sheets by itself as soon as the organizer starts the event."}
                    </p>
                </div>
            </div>
        </>
    );
}

// Persistent layout: the sidebar and live-update pollers stay mounted between pages
// (no remount, no extra poll request per click).
Waiting.layout = (page) => <PageLayout>{page}</PageLayout>;
