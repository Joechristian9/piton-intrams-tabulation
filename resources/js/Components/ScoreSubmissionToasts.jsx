"use client";

import { useEffect, useRef } from "react";
import { usePage } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import { ADMIN_TOPICS, isStale, reloadPage } from "@/lib/liveVersions";

// Last submission already shown. Kept at module level so moving between
// admin pages doesn't miss or repeat a toast.
let lastSeq = null;

const POLL_MS = 3000;

// For admins: shows a toast whenever a judge submits scores, and refreshes the
// page data whenever scores, finalists or judges changed (see lib/liveVersions.js).
// The page only reloads when something changed, not on every tick.
export default function ScoreSubmissionToasts() {
    const { props } = usePage();
    const isAdmin = props.auth?.user?.role === "admin";

    // Version stamps the current page was built with.
    const liveRef = useRef(props.live);
    liveRef.current = props.live;

    useEffect(() => {
        if (!isAdmin) return;

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
            // Don't poll from a background tab.
            if (document.hidden || inFlight) return schedule();
            inFlight = true;

            try {
                const { data } = await axios.get(
                    route("admin.score_submissions"),
                    { params: { after: lastSeq ?? undefined }, timeout: 10000 }
                );

                data.events.forEach((e) => toast.success(e.message));

                if (
                    data.events.length > 0 ||
                    isStale(data.live, liveRef.current, ADMIN_TOPICS)
                ) {
                    reloadPage();
                }
                lastSeq = data.seq;
            } catch {
                // Network hiccup; try again on the next tick.
            } finally {
                inFlight = false;
                schedule();
            }
        };

        check();

        // Catch up right away when the admin returns to the tab.
        document.addEventListener("visibilitychange", check);

        return () => {
            stopped = true;
            clearTimeout(timer);
            document.removeEventListener("visibilitychange", check);
        };
    }, [isAdmin]);

    return null;
}
