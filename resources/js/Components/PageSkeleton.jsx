import React from "react";
import { cn } from "@/lib/utils";

// Loading placeholders shaped like the page being opened. Plain markup + the CSS
// `.skeleton` class (app.css): no images, no requests, a compositor-only shimmer.

const Bone = ({ className }) => <div className={cn("skeleton rounded-md", className)} />;

// A judge's scoring page: a heading, group tabs and candidate cards.
function CardsSkeleton() {
    return (
        <>
            <Bone className="mx-auto h-8 w-56" />
            <div className="mx-auto mt-6 flex justify-center gap-3">
                <Bone className="h-10 w-24 rounded-full" />
                <Bone className="h-10 w-24 rounded-full" />
            </div>
            <div className="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
                {Array.from({ length: 5 }, (_, i) => (
                    <div key={i} className={cn("flex flex-col items-center gap-3 rounded-xl border border-neutral-800 p-4", i > 1 && "hidden sm:flex")}>
                        <Bone className="h-72 w-full" />
                        <Bone className="h-3 w-10" />
                        <Bone className="h-5 w-3/4" />
                        <Bone className="h-4 w-1/2" />
                        <Bone className="mt-2 h-10 w-28 rounded-full" />
                    </div>
                ))}
            </div>
        </>
    );
}

// Results pages: a heading, a toolbar and table rows with an avatar.
function TableSkeleton() {
    return (
        <>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Bone className="h-8 w-64" />
                <Bone className="h-10 w-32 rounded-full" />
            </div>
            <div className="mt-6 overflow-hidden rounded-xl border border-neutral-800">
                <div className="flex gap-4 border-b border-neutral-800 p-3">
                    <Bone className="h-4 w-8" />
                    <Bone className="h-4 w-40" />
                    <Bone className="ml-auto h-4 w-16" />
                    <Bone className="hidden h-4 w-16 sm:block" />
                    <Bone className="hidden h-4 w-16 sm:block" />
                </div>
                {Array.from({ length: 8 }, (_, i) => (
                    <div key={i} className="flex items-center gap-4 border-b border-neutral-800/60 p-3 last:border-0">
                        <Bone className="h-4 w-8" />
                        <Bone className="h-8 w-8 rounded-full" />
                        <Bone className="h-4 w-40" />
                        <Bone className="ml-auto h-4 w-14" />
                        <Bone className="hidden h-4 w-14 sm:block" />
                        <Bone className="hidden h-4 w-14 sm:block" />
                    </div>
                ))}
            </div>
        </>
    );
}

// Everything else (events, setup, notify, theme): a heading and content blocks.
function DefaultSkeleton() {
    return (
        <>
            <div className="flex items-center justify-between gap-3">
                <Bone className="h-8 w-48" />
                <Bone className="h-11 w-36 rounded-lg" />
            </div>
            <div className="mt-6 grid gap-4 lg:grid-cols-2">
                {Array.from({ length: 4 }, (_, i) => (
                    <div key={i} className="space-y-3 rounded-xl border border-neutral-800 p-5">
                        <Bone className="h-6 w-2/3" />
                        <Bone className="h-4 w-1/2" />
                        <div className="flex gap-2 pt-2">
                            <Bone className="h-11 w-24 rounded-lg" />
                            <Bone className="h-11 w-24 rounded-lg" />
                        </div>
                    </div>
                ))}
            </div>
        </>
    );
}

const VARIANTS = { cards: CardsSkeleton, table: TableSkeleton, default: DefaultSkeleton };

/** Which skeleton fits a URL: scoring pages show cards, results pages a table; none for the home hero. */
export function skeletonFor(url) {
    const path = typeof url === "string" ? url : url?.pathname ?? "";
    if (path === "/home") return null;
    if (path.startsWith("/score/")) return "cards";
    if (path.includes("/results/")) return "table";
    return "default";
}

export default function PageSkeleton({ variant = "default", className }) {
    const Variant = VARIANTS[variant] ?? DefaultSkeleton;
    return (
        <div role="status" aria-live="polite" className={cn("p-4 md:p-8", className)}>
            <span className="sr-only">Loading…</span>
            <div aria-hidden="true">
                <Variant />
            </div>
        </div>
    );
}
