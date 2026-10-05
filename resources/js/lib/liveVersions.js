import { router } from "@inertiajs/react";

// Live updates: the server keeps a version stamp per kind of change
// (App\Support\LiveVersions). Every page gets the stamps it was built with as the
// shared `live` prop, and the pollers get the current ones. A difference in a topic
// the page cares about means its data is out of date, so the page reloads itself.

// Judges only need finalist changes (finals pages and the sidebar); reloading their
// scoring page for anything else would just interrupt them.
export const JUDGE_TOPICS = ["finalists"];
export const ADMIN_TOPICS = ["finalists", "judges", "scores"];

export function isStale(serverLive, pageLive, topics) {
    if (!serverLive) return false;
    return topics.some((t) => (serverLive[t] ?? null) !== (pageLive?.[t] ?? null));
}

let reloading = false;

// Re-fetch the current page's props (shared ones included, so `live` catches up).
export function reloadPage() {
    if (reloading) return;
    reloading = true;
    router.reload({
        preserveScroll: true,
        onFinish: () => {
            reloading = false;
        },
    });
}
