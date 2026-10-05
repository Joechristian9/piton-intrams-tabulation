import React, { useState } from "react";
import { Link } from "@inertiajs/react";
import { ArrowLeft, Play, Square } from "lucide-react";
import PageLayout from "@/Layouts/PageLayout";
import PasswordConfirmDialog from "@/Components/PasswordConfirmDialog";
import Settings from "./Tabs/Settings";
import usePasswordAction from "./usePasswordAction";
import { STATUS_STYLES } from "./Index";

// Tabs added as each part of event setup exists.
const TABS = [["settings", "Settings"]];

const readTab = () => {
    try {
        return new URLSearchParams(window.location.search).get("tab") ?? "settings";
    } catch {
        return "settings";
    }
};

export default function Edit(props) {
    const { event, locks } = props;
    const [tab, setTab] = useState(readTab);
    const { ask, dialogProps } = usePasswordAction();

    const choose = (key) => {
        setTab(key);
        try {
            const url = new URL(window.location.href);
            url.searchParams.set("tab", key);
            window.history.replaceState(window.history.state, "", url);
        } catch {
            // Remembering the tab is only a convenience.
        }
    };

    const live = event.status === "live";

    return (
        <PageLayout>
            <div className="p-4 text-white md:p-8">
                <Link href={route("admin.events.index")} className="inline-flex min-h-11 items-center gap-1 text-sm text-gray-300 hover:text-white">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" /> All events
                </Link>

                <div className="mt-2 mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <h2 className="text-xl font-bold">{event.name}</h2>
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold uppercase ${STATUS_STYLES[event.status]}`}>
                            {event.status}
                        </span>
                    </div>
                    <button
                        type="button"
                        onClick={() =>
                            ask(
                                live
                                    ? {
                                          url: route("admin.events.close", event.id),
                                          title: `Close ${event.name}?`,
                                          description: "Judges can no longer score until you start it again.",
                                          confirmLabel: "Close event",
                                          success: "Event closed.",
                                      }
                                    : {
                                          url: route("admin.events.start", event.id),
                                          title: `Start ${event.name}?`,
                                          description: "Its judges can start scoring right away.",
                                          confirmLabel: "Start event",
                                          success: "Event started.",
                                      }
                            )
                        }
                        className={`inline-flex min-h-11 items-center gap-2 rounded-lg px-4 font-semibold ${
                            live ? "border border-neutral-600 bg-neutral-800 hover:bg-neutral-700" : "bg-green-600 hover:bg-green-500"
                        }`}
                    >
                        {live ? <Square className="h-4 w-4" aria-hidden="true" /> : <Play className="h-4 w-4" aria-hidden="true" />}
                        {live ? "Close event" : "Start event"}
                    </button>
                </div>

                <div role="tablist" aria-label="Event setup" className="mb-6 flex flex-wrap gap-2 border-b border-neutral-700">
                    {TABS.map(([key, label]) => (
                        <button
                            key={key}
                            role="tab"
                            type="button"
                            aria-selected={tab === key}
                            onClick={() => choose(key)}
                            className={`-mb-px min-h-11 border-b-2 px-4 text-sm font-medium ${
                                tab === key ? "border-yellow-400 text-yellow-300" : "border-transparent text-gray-300 hover:text-white"
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                <div role="tabpanel" className="max-w-3xl">
                    {tab === "settings" && <Settings event={event} locks={locks} />}
                </div>
            </div>

            {dialogProps && <PasswordConfirmDialog {...dialogProps} />}
        </PageLayout>
    );
}
