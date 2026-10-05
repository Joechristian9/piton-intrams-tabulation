"use client";
import React, { useState, useEffect } from "react";
import { Head, usePage } from "@inertiajs/react";
import {
    Sidebar,
    SidebarBody,
    SidebarLink,
    Logo,
    LogoIcon,
    SidebarHeader,
} from "@/Components/ui/sidebar";
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import {
    ListChecks,
    Shirt,
    Droplet,
    Award,
    User,
    Package,
    Star,
    LogOut,
    Trophy,
    ChevronUp,
    Users,
    BellRing,
    CalendarDays,
} from "lucide-react";

// Icon keys sent by the server-built nav (see app/Support/Navigation.php).
const NAV_ICONS = {
    category: ListChecks,
    trophy: Trophy,
    events: CalendarDays,
    bell: BellRing,
    users: Users,
};

export default function SidebarMain({ children }) {
    const [open, setOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);

    const { props, url } = usePage();
    const user = props.auth?.user; // ✅ get current logged-in user
    const finalistsSet = Boolean(props.finalistsSet);
    // Multi-event nav from the server; the old hard-coded menus remain for users
    // without an event until every page has moved over.
    const nav = props.nav ?? { event: null, sections: [] };
    const useServerNav = Boolean(nav.event) || nav.sections.length > 0;

    // Close the logout menu when the sidebar collapses.
    useEffect(() => {
        if (!open) setUserMenuOpen(false);
    }, [open]);

    // A link is active when its route matches the current page path.
    const currentPath = url.split("?")[0];
    const isActive = (routeName) =>
        route(routeName, undefined, false) === currentPath;

    const renderLinks = (links) =>
        links.map((link, idx) => {
            const active = isActive(link.route);
            const iconElement = React.cloneElement(link.icon, {
                className: cn(
                    "h-5 w-5 shrink-0",
                    active
                        ? "text-amber-500 dark:text-amber-400"
                        : "text-neutral-700 dark:text-neutral-200",
                ),
            });

            return (
                <SidebarLink
                    key={idx}
                    active={active}
                    link={{
                        label: link.label,
                        icon: iconElement,
                        href: "#", // href is required but will be handled via onClick
                        onClick: (e) => {
                            e.preventDefault();
                            router.get(route(link.route));
                        },
                    }}
                />
            );
        });

    useEffect(() => {
        if (user) {
            console.log("User iD:", user.id);
            console.log("User Name:", user.name);
            console.log("User Role:", user.role);
        } else {
            console.log("No authenticated user found.");
        }
    }, [user]);

    // Main links — dynamic based on user role
    const mainLinks =
        user?.role === "admin"
            ? [
                  {
                      label: "Production Number",
                      icon: <ListChecks />,
                      route: "admin.production_number", // ✅ Use route name
                  },
                  {
                      label: "Sports Wear",
                      icon: <Shirt />,
                      route: "admin.casual_wear",
                  },
                  {
                      label: "Swim Wear",
                      icon: <Droplet />,
                      route: "admin.swim_wear",
                  },
                  {
                      label: "Formal Wear",
                      icon: <Award />,
                      route: "admin.formal_wear",
                  },
                  {
                      label: "Casual Interview",
                      icon: <User />, // choose an icon, e.g., User
                      route: "admin.closed_door_interview",
                  },
                  {
                      label: "Top Three Selection ",
                      icon: <Trophy />, // choose an icon, e.g., Star
                      route: "admin.top_five_selection",
                  },
              ]
            : [
                  {
                      label: "Production Number",
                      icon: <ListChecks />,
                      route: "production_number",
                  },
                  {
                      label: "Sports Wear",
                      icon: <Shirt />,
                      route: "casual_wear",
                  },
                  { label: "Swim Wear", icon: <Droplet />, route: "swim_wear" },
                  {
                      label: "Formal Wear",
                      icon: <Award />,
                      route: "formal_wear",
                  },
                  {
                      label: "Casual Interview",
                      icon: <User />,
                      route: "closed_door_interview",
                  },
              ];

    const top5Links =
        user?.role === "admin"
            ? [
                  {
                      label: "Beauty of the Face and Figure",
                      icon: <User />,
                      route: "admin.beauty_face_figure",
                  },
                  {
                      label: "Delivery",
                      icon: <Package />,
                      route: "admin.delivery",
                  },
                  {
                      label: "Over-all Appeal / X-factor",
                      icon: <Star />,
                      route: "admin.overall_appeal",
                  },
                  {
                      label: "Top Three Finalist",
                      icon: <Trophy />,
                      route: "admin.top_five_finalist",
                  },
              ]
            : [
                  {
                      label: "Beauty of the Face and Figure",
                      icon: <User />,
                      route: "beauty_face_figure",
                  },
                  { label: "Delivery", icon: <Package />, route: "delivery" },
                  {
                      label: "Over-all Appeal / X-factor",
                      icon: <Star />,
                      route: "overall_appeal",
                  },
              ];

    const managementLinks =
        user?.role === "admin"
            ? [
                  {
                      label: "Judges",
                      icon: <Users />,
                      route: "admin.judges.index",
                  },
                  {
                      label: "Notify Judges",
                      icon: <BellRing />,
                      route: "admin.notify_judges",
                  },
              ]
            : [];

    const renderNavItems = (items) =>
        items.map((item) => {
            const active = item.href === currentPath;
            const Icon = NAV_ICONS[item.icon] ?? ListChecks;

            return (
                <SidebarLink
                    key={item.href}
                    active={active}
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
                            router.get(item.href);
                        },
                    }}
                />
            );
        });

    // The browser tab is named after the active sidebar item, so tab and menu always match.
    const activeLink = useServerNav
        ? nav.sections
              .flatMap((s) => s.items)
              .find((item) => item.href === currentPath)
        : [...mainLinks, ...top5Links, ...managementLinks].find((link) =>
              isActive(link.route),
          );

    return (
        <div className="dark">
            {activeLink && <Head title={activeLink.label.trim()} />}
            <div
                className={cn(
                    "flex flex-1 flex-col overflow-hidden rounded-md border border-neutral-200 bg-gray-100 md:flex-row dark:border-neutral-700 dark:bg-neutral-800 w-full h-screen",
                )}
            >
                <Sidebar open={open} setOpen={setOpen}>
                    <SidebarBody className="justify-between gap-6">
                        <div className="flex flex-col min-h-0 flex-1 overflow-x-hidden overflow-y-auto">
                            {/* Logo at the top */}
                            {open ? <Logo /> : <LogoIcon />}

                            {useServerNav ? (
                                <>
                                    {nav.event && open && !nav.events && (
                                        <p className="mt-4 truncate px-1 text-sm font-semibold text-yellow-400">
                                            {nav.event.name}
                                        </p>
                                    )}
                                    {/* Admins pick which event the result pages show. */}
                                    {nav.events && open && (
                                        <label className="mt-4 block px-1">
                                            <span className="text-xs font-medium uppercase tracking-wider text-neutral-400">
                                                Event
                                            </span>
                                            <select
                                                value={nav.event.id}
                                                onChange={(e) => {
                                                    const picked = nav.events.find(
                                                        (ev) => ev.id === Number(e.target.value)
                                                    );
                                                    router.get(
                                                        picked.rounds === 2
                                                            ? route("admin.results.round1", picked.id)
                                                            : route("admin.results.standings", picked.id)
                                                    );
                                                }}
                                                className="mt-1 block min-h-11 w-full rounded-lg border-neutral-600 bg-neutral-900 text-sm font-semibold text-yellow-400 focus:border-yellow-400 focus:ring-yellow-400"
                                            >
                                                {nav.events.map((ev) => (
                                                    <option key={ev.id} value={ev.id}>
                                                        {ev.name}
                                                        {ev.status === "live" ? " (live)" : ""}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                    )}
                                    {nav.sections.map((section) => (
                                        <React.Fragment key={section.label}>
                                            <SidebarHeader
                                                label={section.label}
                                            />
                                            <div className="mt-2 flex flex-col gap-2">
                                                {renderNavItems(section.items)}
                                            </div>
                                        </React.Fragment>
                                    ))}
                                </>
                            ) : (
                                <>
                                    {/* Main section header */}
                                    <SidebarHeader label="Top 3 Selection" />

                                    {/* Main links */}
                                    <div className="mt-2 flex flex-col gap-2">
                                        {renderLinks(mainLinks)}
                                    </div>

                                    {/* Top 3 Finalist section: hidden until the admin sets the finalists */}
                                    {finalistsSet && (
                                        <>
                                            <SidebarHeader label="Top 3 Finalist" />
                                            <div className="mt-2 flex flex-col gap-2">
                                                {renderLinks(top5Links)}
                                            </div>
                                        </>
                                    )}

                                    {/* Admin-only management links */}
                                    {user?.role === "admin" && (
                                        <>
                                            <SidebarHeader label="Management" />
                                            <div className="mt-2 flex flex-col gap-2">
                                                {renderLinks(managementLinks)}
                                            </div>
                                        </>
                                    )}
                                </>
                            )}
                        </div>

                        {/* Footer: click the user to show Logout */}
                        <div className="shrink-0 flex flex-col gap-1 border-t border-neutral-200 dark:border-neutral-700 pt-3">
                            {userMenuOpen && (
                                <SidebarLink
                                    className="text-red-500"
                                    link={{
                                        label: "Logout",
                                        href: "#",
                                        icon: (
                                            <LogOut className="h-5 w-5 shrink-0 text-red-500" />
                                        ),
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
                                            {`${
                                                user?.role === "admin"
                                                    ? "Admin"
                                                    : "Judge"
                                            }: ${user?.name || "User"}`}
                                            <ChevronUp
                                                className={cn(
                                                    "h-4 w-4 transition-transform",
                                                    !userMenuOpen &&
                                                        "rotate-180",
                                                )}
                                            />
                                        </span>
                                    ),
                                    href: "#",
                                    icon: (
                                        <picture className="contents">
                                            <source
                                                srcSet="/isu-logo.webp"
                                                type="image/webp"
                                            />
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

                <div className="flex-1 flex flex-col h-full border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900 overflow-y-auto">
                    {children}
                </div>
            </div>
        </div>
    );
}
