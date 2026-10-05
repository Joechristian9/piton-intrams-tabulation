"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { Link, router, usePage } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import { ArrowRight, BellRing, X } from "lucide-react";
import { JUDGE_TOPICS, isStale, reloadPage } from "@/lib/liveVersions";

// Link for a call: a category of the judge's event, or an old route name.
const hrefOf = (e) =>
    e.category_id ? route("score.show", e.category_id) : e.route ? route(e.route) : null;

// Highest notification id already toasted in this browser tab. Module level, so
// moving between pages doesn't repeat a toast.
let lastToastedId = null;

const POLL_MS = 3000;

const dismissedKey = (userId) => `piton-dismissed-calls:${userId}`;

function readDismissed(userId) {
    try {
        return JSON.parse(localStorage.getItem(dismissedKey(userId))) ?? [];
    } catch {
        return [];
    }
}

function saveDismissed(userId, ids) {
    try {
        localStorage.setItem(dismissedKey(userId), JSON.stringify(ids.slice(-50)));
    } catch {
        // Storage unavailable: the banner just reappears after a reload.
    }
}

// For judges: shows the admin's "please score ..." notifications as a toast
// when they arrive and as a banner until dismissed.
export default function JudgeNotifications() {
    const { props, url } = usePage();
    const user = props.auth?.user;
    const isJudge = user?.role === "judge";

    // Version stamps the current page was built with (see lib/liveVersions.js).
    const liveRef = useRef(props.live);
    liveRef.current = props.live;

    const [events, setEvents] = useState([]);
    const [dismissed, setDismissed] = useState(() => (isJudge ? readDismissed(user.id) : []));

    const dismiss = useCallback(
        (id) => {
            setDismissed((current) => {
                const next = [...current, id];
                saveDismissed(user.id, next);
                return next;
            });
        },
        [user?.id]
    );

    useEffect(() => {
        if (!isJudge) return;

        let timer = null;
        let inFlight = false;
        let stopped = false;

        // The next check is scheduled only after the previous one finishes, so a
        // slow server never gets a pile-up of overlapping requests.
        const schedule = () => {
            clearTimeout(timer);
            if (!stopped) timer = setTimeout(check, POLL_MS);
        };

        const check = async () => {
            if (document.hidden || inFlight) return schedule();
            inFlight = true;

            try {
                const { data } = await axios.get(route("judge.notifications"), {
                    timeout: 10000,
                });

                // The admin set or changed the finalists: refresh this page's data.
                if (isStale(data.live, liveRef.current, JUDGE_TOPICS)) reloadPage();

                // Only re-render when the list actually changed (most polls return the same).
                setEvents((current) =>
                    current.length === data.events.length &&
                    current.at(-1)?.id === data.events.at(-1)?.id
                        ? current
                        : data.events
                );

                // First check in this tab: remember where we are, don't toast old calls.
                if (lastToastedId !== null) {
                    data.events
                        .filter((e) => e.id > lastToastedId)
                        .forEach((e) =>
                            toast.info(e.message, {
                                description: `From ${e.sender}`,
                                duration: 8000,
                                action: hrefOf(e)
                                    ? {
                                          label: `Go to ${e.label}`,
                                          onClick: () => {
                                              dismiss(e.id);
                                              router.visit(hrefOf(e));
                                          },
                                      }
                                    : undefined,
                            })
                        );
                }
                lastToastedId = Math.max(lastToastedId ?? 0, data.seq);
            } catch {
                // Network hiccup; try again on the next tick.
            } finally {
                inFlight = false;
                schedule();
            }
        };

        check();
        document.addEventListener("visibilitychange", check);

        return () => {
            stopped = true;
            clearTimeout(timer);
            document.removeEventListener("visibilitychange", check);
        };
    }, [isJudge, dismiss]);

    if (!isJudge) return null;

    // Show the newest notification the judge hasn't dismissed.
    const current = [...events].reverse().find((e) => !dismissed.includes(e.id));
    if (!current) return null;

    const onTargetPage =
        hrefOf(current) && new URL(hrefOf(current)).pathname === url.split("?")[0];

    return (
        <div
            role="status"
            aria-live="polite"
            // In the page flow at the top (not floating), so it never covers the
            // Female/Male tabs or the scoring cards; the toast handles the live alert.
            className="mx-auto w-full max-w-2xl px-4 pt-4"
        >
            <div className="flex items-start gap-3 rounded-2xl border border-yellow-400/50 bg-neutral-900/95 p-4 text-white shadow-[0_0_32px_rgba(250,204,21,0.2)] backdrop-blur">
                <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-yellow-400/15 text-yellow-400">
                    <BellRing className="h-5 w-5 motion-safe:animate-pulse" aria-hidden="true" />
                </span>

                <div className="min-w-0 flex-1">
                    <p className="text-xs font-medium uppercase tracking-wider text-yellow-300">
                        Message from {current.sender}
                    </p>
                    <p className="mt-1 text-sm text-white">{current.message}</p>

                    {hrefOf(current) && !onTargetPage && (
                        <Link
                            href={hrefOf(current)}
                            onClick={() => dismiss(current.id)}
                            className="mt-3 inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full bg-yellow-400 px-4 text-sm font-semibold text-black transition duration-200 hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-300 focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-900"
                        >
                            Go to {current.label}
                            <ArrowRight className="h-4 w-4" aria-hidden="true" />
                        </Link>
                    )}
                </div>

                <button
                    type="button"
                    onClick={() => dismiss(current.id)}
                    aria-label="Dismiss notification"
                    className="grid h-11 w-11 shrink-0 cursor-pointer place-items-center rounded-full text-gray-400 transition-colors duration-200 hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-300"
                >
                    <X className="h-5 w-5" aria-hidden="true" />
                </button>
            </div>
        </div>
    );
}
