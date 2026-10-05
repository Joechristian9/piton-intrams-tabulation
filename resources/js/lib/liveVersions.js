import { router } from "@inertiajs/react";

// Live updates: the server keeps a version stamp per kind of change
// (App\Support\LiveVersions). Every page gets the stamps it was built with as the
// shared `live` prop, and the pollers get the current ones. A difference in a topic
// the page cares about means its data is out of date, so the page reloads itself.

// Stamps are per event. Judges need event changes (status,
// categories, candidates) and finalist changes; reloading their scoring page when
// other judges submit would just interrupt them.
export const JUDGE_TOPICS = ["event", "finalists"];
export const ADMIN_TOPICS = ["event", "finalists", "scores"];

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
