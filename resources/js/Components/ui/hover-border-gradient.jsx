"use client";
import React from "react";
import { cn } from "@/lib/utils";

/**
 * A pill button with a light that travels around its border, and a blue glow on
 * hover. Pure CSS: a conic gradient spun by a CSS animation (GPU transform only),
 * so it costs no JavaScript or re-renders. It used to re-render every second via
 * setInterval plus an animation library, on every page that showed one.
 * Respects reduced motion (the light stays still).
 */
export function HoverBorderGradient({
    children,
    containerClassName,
    className,
    as: Tag = "button",
    duration = 1,
    clockwise = true,
    ...props
}) {
    return (
        <Tag
            className={cn(
                "group relative flex rounded-full border content-center bg-black/20 hover:bg-black/10 transition duration-500 dark:bg-white/20 items-center flex-col flex-nowrap gap-10 h-min justify-center overflow-visible p-px decoration-clone w-fit",
                containerClassName,
            )}
            {...props}
        >
            <div className={cn("w-auto text-white z-10 bg-black px-4 py-2 rounded-[inherit]", className)}>{children}</div>
            <span className="absolute inset-0 z-0 overflow-hidden rounded-[inherit]" aria-hidden="true">
                {/* The travelling light: one lap per 4 × duration seconds, like before. */}
                <span
                    className="absolute left-1/2 top-1/2 aspect-square w-[200%] -translate-x-1/2 -translate-y-1/2 animate-spin bg-[conic-gradient(from_0deg,transparent_0deg,rgba(255,255,255,0.95)_30deg,transparent_75deg)] motion-reduce:animate-none"
                    style={{ animationDuration: `${duration * 4}s`, animationDirection: clockwise ? "normal" : "reverse" }}
                />
                {/* Hover: the whole border glows blue. */}
                <span className="absolute inset-0 bg-[radial-gradient(75%_181%_at_50%_50%,#3275F8_0%,rgba(255,255,255,0)_100%)] opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
            </span>
            <div className="bg-black absolute z-1 flex-none inset-[2px] rounded-[100px]" />
        </Tag>
    );
}
