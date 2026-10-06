"use client";
import { cn } from "@/lib/utils";
import React, { useState, useEffect, useRef, createContext, useContext } from "react";
import { AnimatePresence, motion } from "motion/react";
import { Menu, X } from "lucide-react";
import { router } from "@inertiajs/react";

const SidebarContext = createContext(undefined);

export const useSidebar = () => {
    const context = useContext(SidebarContext);
    if (!context) {
        throw new Error("useSidebar must be used within a SidebarProvider");
    }
    return context;
};

export const SidebarProvider = ({
    children,
    open: openProp,
    setOpen: setOpenProp,
    animate = true,
}) => {
    const [openState, setOpenState] = useState(false);

    const open = openProp !== undefined ? openProp : openState;
    const setOpen = setOpenProp !== undefined ? setOpenProp : setOpenState;

    return (
        <SidebarContext.Provider value={{ open, setOpen, animate: animate }}>
            {children}
        </SidebarContext.Provider>
    );
};

export const Sidebar = ({ children, open, setOpen, animate }) => {
    return (
        <SidebarProvider open={open} setOpen={setOpen} animate={animate}>
            {children}
        </SidebarProvider>
    );
};

export const SidebarBody = (props) => {
    return (
        <>
            <DesktopSidebar {...props} />
            <MobileSidebar {...props} />
        </>
    );
};

export const DesktopSidebar = ({ className, children, ...props }) => {
    const { open, setOpen, animate } = useSidebar();
    return (
        <motion.nav
            aria-label="Main"
            className={cn(
                "h-full px-4 py-4 hidden  md:flex md:flex-col bg-neutral-100 dark:bg-neutral-800 w-[300px] shrink-0",
                className
            )}
            animate={{
                width: animate ? (open ? "300px" : "60px") : "300px",
            }}
            onMouseEnter={() => setOpen(true)}
            onMouseLeave={() => setOpen(false)}
            // Keyboard users: expand while focus is inside, collapse when it leaves.
            onFocus={() => setOpen(true)}
            onBlur={(e) => {
                if (!e.currentTarget.contains(e.relatedTarget)) setOpen(false);
            }}
            {...props}
        >
            {children}
        </motion.nav>
    );
};

// Phones and small tablets: a top bar with a menu button that opens the
// sidebar as a full-screen panel (Escape or the close button shuts it).
export const MobileSidebar = ({ className, children, ...props }) => {
    const { open, setOpen } = useSidebar();
    const menuButton = useRef(null);
    const closeButton = useRef(null);
    const wasOpen = useRef(false);

    useEffect(() => {
        if (!open) {
            if (wasOpen.current) menuButton.current?.focus();
            wasOpen.current = false;
            return;
        }
        // Only the visible (mobile) panel takes focus; on desktop it isn't rendered.
        if (!closeButton.current?.offsetParent) return;
        wasOpen.current = true;
        closeButton.current.focus();
        const onKey = (e) => e.key === "Escape" && setOpen(false);
        document.addEventListener("keydown", onKey);
        return () => document.removeEventListener("keydown", onKey);
    }, [open, setOpen]);

    return (
        <div
            className="flex h-14 w-full shrink-0 items-center justify-between border-b border-neutral-200 bg-neutral-100 px-4 md:hidden dark:border-neutral-700 dark:bg-neutral-800"
            {...props}
        >
            <span className="flex min-w-0 items-center gap-2">
                <picture className="contents">
                    <source srcSet="/piton-logo.webp" type="image/webp" />
                    <img src="/PITON%20LOGO.png" alt="" width={28} height={28} className="h-7 w-7 shrink-0 object-contain" />
                </picture>
                <span className="truncate font-medium text-black dark:text-white">PITON Tabulation</span>
            </span>
            <button
                ref={menuButton}
                type="button"
                onClick={() => setOpen(true)}
                aria-label="Open menu"
                aria-expanded={open}
                aria-controls="mobile-sidebar"
                className="-mr-2 grid h-11 w-11 place-items-center rounded-lg text-neutral-800 hover:bg-neutral-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 dark:text-neutral-200 dark:hover:bg-neutral-700"
            >
                <Menu className="h-6 w-6" aria-hidden="true" />
            </button>
            <AnimatePresence>
                {open && (
                    <motion.nav
                        id="mobile-sidebar"
                        aria-label="Main"
                        initial={{ x: "-100%", opacity: 0 }}
                        animate={{ x: 0, opacity: 1 }}
                        exit={{ x: "-100%", opacity: 0 }}
                        transition={{ duration: 0.25, ease: "easeInOut" }}
                        className={cn(
                            "fixed inset-0 z-[100] flex h-full w-full flex-col justify-between overflow-y-auto bg-neutral-100 p-4 pt-3 md:hidden dark:bg-neutral-900",
                            className
                        )}
                    >
                        <button
                            ref={closeButton}
                            type="button"
                            onClick={() => setOpen(false)}
                            aria-label="Close menu"
                            className="absolute right-2 top-2 z-50 grid h-11 w-11 place-items-center rounded-lg text-neutral-800 hover:bg-neutral-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 dark:text-neutral-200 dark:hover:bg-neutral-700"
                        >
                            <X className="h-6 w-6" aria-hidden="true" />
                        </button>
                        {children}
                    </motion.nav>
                )}
            </AnimatePresence>
        </div>
    );
};

export const SidebarLink = ({ link, className, active = false, ...props }) => {
    const { open, animate } = useSidebar();

    return (
        <a
            href={link.href}
            onClick={link.onClick} // ✅ attach the onClick from your link
            aria-current={active ? "page" : undefined}
            className={cn(
                "relative flex items-center justify-start gap-2 group/sidebar py-2 px-2 -mx-2 rounded-lg transition-colors",
                active
                    ? "bg-neutral-200 dark:bg-neutral-700/70"
                    : "hover:bg-neutral-200/60 dark:hover:bg-neutral-700/30",
                className
            )}
            {...props}
        >
            {/* Accent bar marking the current page */}
            {active && (
                <span className="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-full bg-amber-400" />
            )}
            {link.icon}
            <motion.span
                animate={{
                    display: animate
                        ? open
                            ? "inline-block"
                            : "none"
                        : "inline-block",
                    opacity: animate ? (open ? 1 : 0) : 1,
                }}
                className={cn(
                    "text-sm group-hover/sidebar:translate-x-1 transition duration-150 whitespace-pre inline-block !p-0 !m-0",
                    active
                        ? "text-black dark:text-white font-semibold"
                        : "text-neutral-700 dark:text-neutral-200"
                )}
            >
                {link.label}
            </motion.span>
        </a>
    );
};

export const Logo = () => {
    return (
        <a
            href={route("dashboard")}
            onClick={(e) => {
                e.preventDefault();
                router.get(route("dashboard"));
            }}
            className="cursor-pointer relative z-20 flex items-center space-x-2 py-1 text-sm font-normal"
        >
            {/* Fixed-size logo container */}
            <div className="h-8 w-8 flex-shrink-0 flex-grow-0 relative">
                <picture>
                    <source srcSet="/piton-logo.webp" type="image/webp" />
                    <img
                        src="/PITON%20LOGO.png"
                        alt="Piton Logo"
                        width={32}
                        height={32}
                        className="h-full w-full object-contain"
                    />
                </picture>
            </div>

            {/* Text label does not affect logo size */}
            <motion.span
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                className="font-medium whitespace-pre text-black dark:text-white"
            >
                PITON Tabulation
            </motion.span>
        </a>
    );
};

export const LogoIcon = () => {
    return (
        <a
            href={route("dashboard")}
            onClick={(e) => {
                e.preventDefault();
                router.get(route("dashboard"));
            }}
            aria-label="PITON Tabulation home"
            className="relative z-20 flex items-center space-x-2 py-1 text-sm font-normal"
        >
            <div className="h-8 w-8 flex-shrink-0 flex-grow-0 relative">
                <picture>
                    <source srcSet="/piton-logo.webp" type="image/webp" />
                    <img
                        src="/PITON%20LOGO.png"
                        alt="Piton Logo"
                        width={32}
                        height={32}
                        className="h-full w-full object-contain"
                    />
                </picture>
            </div>
        </a>
    );
};

export const SidebarHeader = ({ label = "Top 5 Selection" }) => {
    const { open, animate } = useSidebar();

    return (
        <div className="mt-8 px-4 text-sm font-bold uppercase text-neutral-500 dark:text-neutral-400 h-5">
            <motion.span
                animate={{
                    display: animate
                        ? open
                            ? "inline-block"
                            : "none"
                        : "inline-block",
                    opacity: animate ? (open ? 1 : 0) : 1,
                }}
                className="text-neutral-700 dark:text-neutral-200 text-sm group-hover/sidebar:translate-x-1 transition duration-150 whitespace-pre inline-block !p-0 !m-0"
            >
                {label}
            </motion.span>
        </div>
    );
};
