// PageLayout.jsx
"use client";
import React from "react";
import { Toaster } from "sonner";
import SidebarMain from "@/Components/SidebarMain";
import ScoreSubmissionToasts from "@/Components/ScoreSubmissionToasts";
import JudgeNotifications from "@/Components/JudgeNotifications";

export default function PageLayout({ children, auth }) {
    const user = auth?.user; // extract user here
    return (
        <SidebarMain user={user}>
            <JudgeNotifications />
            {children}
            <ScoreSubmissionToasts />
            <Toaster position="top-right" />
        </SidebarMain>
    );
}
