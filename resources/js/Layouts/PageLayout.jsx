// PageLayout.jsx
"use client";
import React from "react";
import { Toaster } from "sonner";
import SidebarMain from "@/Components/SidebarMain";

export default function PageLayout({ children, auth }) {
    const user = auth?.user; // extract user here
    return (
        <SidebarMain user={user}>
            {children}
            <Toaster position="top-right" />
        </SidebarMain>
    );
}
