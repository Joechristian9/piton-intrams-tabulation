// PageLayout.jsx
"use client";
import React from "react";
import { Toaster } from "sonner";
import SidebarMain from "@/Components/SidebarMain";
import ScoreSubmissionToasts from "@/Components/ScoreSubmissionToasts";
import JudgeNotifications from "@/Components/JudgeNotifications";
import NavigationSkeleton from "@/Components/NavigationSkeleton";

// The signed-in shell (sidebar, live-update pollers, toasts). Pages use it as a
// persistent layout (`Page.layout = (page) => <PageLayout>{page}</PageLayout>`), so it
// stays mounted while the user moves between pages: no sidebar remount and no extra
// poll request on every click. Everything here reads the current page via usePage().
export default function PageLayout({ children }) {
    return (
        <SidebarMain overlay={<NavigationSkeleton />}>
            <JudgeNotifications />
            {children}
            <ScoreSubmissionToasts />
            <Toaster position="top-right" />
        </SidebarMain>
    );
}
