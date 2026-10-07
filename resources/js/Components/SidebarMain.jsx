"use client";
import React, { useState, useEffect, useRef } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import {
    Sidebar,
    SidebarBody,
    SidebarLink,
    Logo,
    LogoIcon,
    SidebarHeader,
} from "@/Components/ui/sidebar";
import { cn } from "@/lib/utils";
import categoryIcon from "@/lib/categoryIcon";
import {
    ListChecks,
    LogOut,
    Trophy,
    ChevronUp,
    Users,
    BellRing,
    CalendarDays,
    Medal,
    Palette,
} from "lucide-react";

// Icon keys sent by the server-built nav (see app/Support/Navigation.php).
// Categories get the icon the admin picked, else one from their name (lib/categoryIcon.js).
const NAV_ICONS = {
    trophy: Trophy,
    medal: Medal,
    events: CalendarDays,
    bell: BellRing,
    palette: Palette,
    users: Users,
};

// The sidebar: items come from the server (`nav` shared prop) for the user's
// event — a judge's categories, or the result pages of the event an admin has open.
export default function SidebarMain({ children, overlay = null }) {
    const [open, setOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);

    const { props, url } = usePage();
    const user = props.auth?.user;
    const nav = props.nav ?? { event: null, sections: [] };

    // Close the logout menu when the sidebar collapses.
    useEffect(() => {
        if (!open) setUserMenuOpen(false);
    }, [open]);

    const currentPath = url.split("?")[0];

    // Position of each category in the whole menu, so unmatched names still get distinct icons.
    const categoryIndex = new Map(
        nav.sections
            .flatMap((s) => s.items)
            .filter((item) => item.icon === "category")
            .map((item, i) => [item.href, i]),
    );

    // Fetch a page before the click lands: after the pointer rests on an item for 75 ms
    // (as Inertia's own <Link prefetch>), or right away on touch / keyboard focus. The
    // click then shows the prefetched page at once. Kept 10 s; the live-update pollers
    // still reload it if its data changed after that.
    const hoverTimer = useRef(null);
    const prefetch = (href) => {
        if (href !== currentPath) router.prefetch(href, { method: "get" }, { cacheFor: "10s" });
    };
    const prefetchProps = (href) => ({
        onMouseEnter: () => {
            clearTimeout(hoverTimer.current);
            hoverTimer.current = setTimeout(() => prefetch(href), 75);
        },
        onMouseLeave: () => clearTimeout(hoverTimer.current),
        onTouchStart: () => prefetch(href),
        onFocus: () => prefetch(href),
    });

    const renderNavItems = (items) =>
        items.map((item) => {
            const active = item.href === currentPath;
            const Icon =
                item.icon === "category"
                    ? categoryIcon(item.label, categoryIndex.get(item.href), item.iconKey)
                    : (NAV_ICONS[item.icon] ?? ListChecks);

            return (
                <SidebarLink
                    key={item.href}
                    active={active}
                    {...prefetchProps(item.href)}
                    link={{
                        label: item.label,
                        icon: (
                            <Icon
                                className={cn(
                                    "h-5 w-5 shrink-0",
                                    active
                                        ? "text-amber-500 dark:text-amber-400"
                                        : "text-neutral-700 dark:text-neutral-200",
                                )}
                            />
                        ),
                        href: item.href,
                        onClick: (e) => {
                            e.preventDefault();
                            setOpen(false); // close the phone menu panel
                            router.get(item.href);
                        },
                    }}
                />
            );
        });

    // The browser tab is named after the active sidebar item, so tab and menu always match.
    const activeLink = nav.sections
        .flatMap((s) => s.items)
        .find((item) => item.href === currentPath);

    return (
        // color-scheme: dark gives native scrollbars and form controls dark styling
        // (the default light scrollbar showed as a white bar in the collapsed sidebar).
        <div className="dark [color-scheme:dark]">
            {activeLink && <Head title={activeLink.label.trim()} />}
            <div className="flex h-screen w-full flex-1 flex-col overflow-hidden rounded-md border border-neutral-200 bg-gray-100 md:flex-row dark:border-neutral-700 dark:bg-neutral-800">
                <Sidebar open={open} setOpen={setOpen}>
                    <SidebarBody className="justify-between gap-6">
                        <div className="flex min-h-0 flex-1 flex-col overflow-x-hidden overflow-y-auto [scrollbar-width:thin]">
                            {open ? <Logo /> : <LogoIcon />}

                            {nav.event && open && (
                                <p className="mt-4 truncate px-1 text-sm font-semibold text-yellow-400">
                                    {nav.event.name}
                                </p>
                            )}

                            {nav.sections.map((section) => (
                                <React.Fragment key={section.label}>
                                    <SidebarHeader label={section.label} />
                                    <div className="mt-2 flex flex-col gap-2">
                                        {renderNavItems(section.items)}
                                    </div>
                                </React.Fragment>
                            ))}
                        </div>

                        {/* Footer: click the user to show Logout */}
                        <div className="flex shrink-0 flex-col gap-1 border-t border-neutral-200 pt-3 dark:border-neutral-700">
                            {userMenuOpen && (
                                <SidebarLink
                                    className="text-red-500"
                                    link={{
                                        label: "Logout",
                                        href: "#",
                                        icon: <LogOut className="h-5 w-5 shrink-0 text-red-500" />,
                                        onClick: (e) => {
                                            e.preventDefault();
                                            router.post("/logout");
                                        },
                                    }}
                                />
                            )}
                            <SidebarLink
                                aria-expanded={userMenuOpen}
                                link={{
                                    label: (
                                        <span className="flex items-center gap-2">
                                            {`${user?.role === "admin" ? "Admin" : "Judge"}: ${user?.name || "User"}`}
                                            <ChevronUp
                                                className={cn(
                                                    "h-4 w-4 transition-transform",
                                                    !userMenuOpen && "rotate-180",
                                                )}
                                            />
                                        </span>
                                    ),
                                    href: "#",
                                    icon: (
                                        <picture className="contents">
                                            <source srcSet="/isu-logo.webp" type="image/webp" />
                                            <img
                                                src="/isu-logo.png"
                                                className="h-7 w-7 shrink-0 rounded-full"
                                                width={50}
                                                height={50}
                                                alt="Avatar"
                                            />
                                        </picture>
                                    ),
                                    onClick: (e) => {
                                        e.preventDefault();
                                        setUserMenuOpen((o) => !o);
                                    },
                                }}
                            />
                        </div>
                    </SidebarBody>
                </Sidebar>

                <div className="relative flex min-h-0 min-w-0 flex-1 flex-col">
                    {/* scroll-region: Inertia resets/restores this scroll position on navigation
                        (the layout is persistent, so the element itself stays the same). */}
                    <main scroll-region="" className="flex min-h-0 min-w-0 flex-1 flex-col overflow-y-auto border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
                        {children}
                    </main>
                    {/* Over the content area, not inside the scrolling <main>: a page-change
                        skeleton always covers what's visible, wherever the old page was scrolled. */}
                    {overlay}
                </div>
            </div>
        </div>
    );
}
