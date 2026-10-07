import React, { useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import PageSkeleton, { skeletonFor } from "@/Components/PageSkeleton";

// Covers the content area with a skeleton of the page being opened while a page
// change is slow. Only for real page changes (not the live-update reloads, which keep
// the page's state, nor prefetches), and only after 120 ms, so prefetched or quick
// pages appear without a flash. Rendered by the persistent layout, outside the page,
// so showing it doesn't re-render the page itself.
const DELAY_MS = 120;

export default function NavigationSkeleton() {
    const [variant, setVariant] = useState(null);

    useEffect(() => {
        let timer = null;
        const hide = () => {
            clearTimeout(timer);
            setVariant(null);
        };

        const offStart = router.on("start", (event) => {
            const visit = event.detail.visit;
            if (visit.method !== "get" || visit.preserveState || visit.prefetch) return;
            clearTimeout(timer);
            timer = setTimeout(() => setVariant(skeletonFor(visit.url)), DELAY_MS);
        });
        const offFinish = router.on("finish", hide);

        return () => {
            offStart();
            offFinish();
            clearTimeout(timer);
        };
    }, []);

    if (!variant) return null;

    return <PageSkeleton variant={variant} className="absolute inset-0 z-30 overflow-hidden bg-neutral-900" />;
}
