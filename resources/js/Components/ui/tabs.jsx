"use client";
import React, { useState } from "react";
import { cn } from "@/lib/utils";

export const Tabs = ({
    tabs: propTabs,
    containerClassName,
    activeTabClassName,
    tabClassName,
    contentClassName,
}) => {
    // Only the active tab's value is kept in state; its content always comes from the
    // latest props, so live updates (router.reload) show fresh data. Storing the tab
    // objects themselves froze the content at the first render.
    const [activeValue, setActiveValue] = useState(propTabs[0].value);
    const active = propTabs.find((tab) => tab.value === activeValue) ?? propTabs[0];

    return (
        <>
            <div
                role="tablist"
                className={cn(
                    "flex flex-row items-center justify-center relative overflow-auto sm:overflow-visible no-visible-scrollbar max-w-full w-full",
                    containerClassName,
                )}
            >
                {propTabs.map((tab) => {
                    const selected = tab.value === active.value;
                    return (
                        <button
                            key={tab.title}
                            type="button"
                            role="tab"
                            aria-selected={selected}
                            onClick={() => setActiveValue(tab.value)}
                            className={cn("relative px-4 py-2 rounded-full", tabClassName)}
                        >
                            {/* The highlight behind the active tab (plain CSS; no animation library). */}
                            <span
                                aria-hidden="true"
                                className={cn(
                                    "absolute inset-0 rounded-full bg-gray-200 dark:bg-zinc-800 transition-opacity duration-200",
                                    activeTabClassName,
                                    selected ? "opacity-100" : "opacity-0",
                                )}
                            />
                            <span className="relative block text-black dark:text-white">{tab.title}</span>
                        </button>
                    );
                })}
            </div>
            <TabContent tab={active} key={active.value} className={cn("mt-20", contentClassName)} />
        </>
    );
};

// Only the active tab is rendered (hidden ones used to mount a full candidate grid,
// photos included). It fades in when the tab changes.
const TabContent = ({ tab, className }) => (
    <div className="relative w-full h-full">
        <div
            role="tabpanel"
            className={cn(
                "w-full h-full absolute top-0 left-0 animate-in fade-in-0 slide-in-from-bottom-4 duration-300 motion-reduce:animate-none",
                className,
            )}
        >
            <h2 className="text-2xl font-bold text-neutral-200 text-center mb-6">{tab.category}</h2>

            {/* Call content as function for reactive re-render */}
            {tab.content && (
                <div className="tab-content">{typeof tab.content === "function" ? tab.content() : tab.content}</div>
            )}
        </div>
    </div>
);
