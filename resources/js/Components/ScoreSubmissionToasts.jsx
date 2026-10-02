"use client";

import { useEffect } from "react";
import { router, usePage } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";

// Last submission already shown. Kept at module level so moving between
// admin pages doesn't miss or repeat a toast.
let lastSeq = null;

// For admins: shows a toast whenever a judge submits scores and refreshes
// the page data at that moment. The page only reloads when something
// changed, instead of re-fetching everything on a timer.
export default function ScoreSubmissionToasts() {
    const isAdmin = usePage().props.auth?.user?.role === "admin";

    useEffect(() => {
        if (!isAdmin) return;

        const check = async () => {
            // Don't poll from a background tab.
            if (document.hidden) return;

            try {
                const { data } = await axios.get(
                    route("admin.score_submissions"),
                    { params: { after: lastSeq ?? undefined } }
                );

                if (data.events.length > 0) {
                    data.events.forEach((e) => toast.success(e.message));
                    router.reload({ preserveScroll: true });
                }
                lastSeq = data.seq;
            } catch {
                // Network hiccup; try again on the next tick.
            }
        };

        check();
        const timer = setInterval(check, 3000);

        // Catch up right away when the admin returns to the tab.
        document.addEventListener("visibilitychange", check);

        return () => {
            clearInterval(timer);
            document.removeEventListener("visibilitychange", check);
        };
    }, [isAdmin]);

    return null;
}
