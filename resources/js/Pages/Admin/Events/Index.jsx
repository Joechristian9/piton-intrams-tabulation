import React, { useState } from "react";
import { Link, router } from "@inertiajs/react";
import { CalendarPlus, Copy, Pencil, Play, Square, Trash2, Trophy } from "lucide-react";
import PageLayout from "@/Layouts/PageLayout";
import Modal from "@/Components/Modal";
import PasswordConfirmDialog from "@/Components/PasswordConfirmDialog";
import Settings from "./Tabs/Settings";
import usePasswordAction from "./usePasswordAction";
import { STATUS_STYLES } from "@/lib/eventStatus";


const button =
    "inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-neutral-600 bg-neutral-800 px-3 text-sm hover:bg-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-300 disabled:cursor-not-allowed disabled:opacity-40";

export default function Index({ events = [] }) {
    const [creating, setCreating] = useState(false);
    const { ask, dialogProps } = usePasswordAction();

    const resultsHref = (e) =>
        e.rounds === 2 ? route("admin.results.round1", e.id) : route("admin.results.standings", e.id);

    return (
        <PageLayout>
            <div className="p-4 text-white md:p-8">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-bold">Events</h2>
                    <button
                        type="button"
                        onClick={() => setCreating(true)}
                        className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-yellow-400 px-4 font-semibold text-black hover:bg-yellow-300"
                    >
                        <CalendarPlus className="h-5 w-5" aria-hidden="true" />
                        New event
                    </button>
                </div>

                {events.length === 0 && (
                    <p className="rounded-xl border border-neutral-700 p-8 text-center text-gray-400">
                        No events yet. Click "New event" to create one.
                    </p>
                )}

                <ul className="grid gap-4 lg:grid-cols-2">
                    {events.map((e) => (
                        <li key={e.id} className="rounded-xl border border-neutral-700 bg-neutral-900 p-5">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <h3 className="truncate text-lg font-semibold">{e.name}</h3>
                                    <p className="text-sm text-gray-400">
                                        {e.code} · {e.rounds === 1 ? "1 round" : "2 rounds"} · {e.candidates} candidates · {e.judges} judges
                                    </p>
                                </div>
                                <span className={`shrink-0 rounded-full px-3 py-1 text-xs font-semibold uppercase ${STATUS_STYLES[e.status]}`}>
                                    {e.status}
                                </span>
                            </div>

                            <div className="mt-4 flex flex-wrap gap-2">
                                <Link href={route("admin.events.edit", e.id)} className={button}>
                                    <Pencil className="h-4 w-4" aria-hidden="true" /> Set up
                                </Link>
                                <Link href={resultsHref(e)} className={button}>
                                    <Trophy className="h-4 w-4" aria-hidden="true" /> Results
                                </Link>
                                {e.status === "live" ? (
                                    <button
                                        type="button"
                                        className={button}
                                        onClick={() =>
                                            ask({
                                                url: route("admin.events.close", e.id),
                                                title: `Close ${e.name}?`,
                                                description: "Judges can no longer score until you start it again.",
                                                confirmLabel: "Close event",
                                                success: "Event closed.",
                                            })
                                        }
                                    >
                                        <Square className="h-4 w-4" aria-hidden="true" /> Close
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        className={button}
                                        onClick={() =>
                                            ask({
                                                url: route("admin.events.start", e.id),
                                                title: `Start ${e.name}?`,
                                                description: "Its judges can start scoring right away.",
                                                confirmLabel: "Start event",
                                                success: "Event started.",
                                            })
                                        }
                                    >
                                        <Play className="h-4 w-4" aria-hidden="true" /> Start
                                    </button>
                                )}
                                <button
                                    type="button"
                                    className={button}
                                    onClick={() => router.post(route("admin.events.duplicate", e.id))}
                                >
                                    <Copy className="h-4 w-4" aria-hidden="true" /> Duplicate
                                </button>
                                <button
                                    type="button"
                                    className={`${button} border-red-500/50 text-red-300`}
                                    disabled={e.hasScores}
                                    title={e.hasScores ? "Events with scores can't be deleted. Close them instead." : undefined}
                                    onClick={() =>
                                        ask({
                                            url: route("admin.events.destroy", e.id),
                                            method: "delete",
                                            title: `Delete ${e.name}?`,
                                            description: "Its groups, categories, candidates, photos and judge accounts are removed. This can't be undone.",
                                            confirmLabel: "Delete event",
                                            danger: true,
                                            success: "Event deleted.",
                                        })
                                    }
                                >
                                    <Trash2 className="h-4 w-4" aria-hidden="true" /> Delete
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>

            <Modal show={creating} onClose={() => setCreating(false)} maxWidth="lg">
                <div className="bg-neutral-900 p-6">
                    <h2 className="mb-4 text-lg font-semibold text-white">New event</h2>
                    <Settings onDone={() => setCreating(false)} />
                </div>
            </Modal>

            {dialogProps && <PasswordConfirmDialog {...dialogProps} />}
        </PageLayout>
    );
}
