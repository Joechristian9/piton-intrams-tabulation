import React, { useEffect } from "react";
import PageLayout from "@/Layouts/PageLayout";
import StarsBackground from "@/Components/backgrounds/stars";
import { cn } from "@/lib/utils";

export default function Dashboard({ auth }) {
    const user = auth?.user;

    // Log the user name and role once when the component mounts
    useEffect(() => {
        if (user) {
            console.log("User iD:", user.id);
            console.log("User Name:", user.name);
            console.log("User Role:", user.role);
        } else {
            console.log("No authenticated user found.");
        }
    }, [user]);
    return (
        <PageLayout user={auth?.user}>
            <div className="relative flex-1 w-full h-full rounded-xl overflow-hidden">
                {/* 🌌 Starry background */}
                <StarsBackground
                    starColor="#ffffff"
                    className={cn(
                        "absolute inset-0 w-full h-full",
                        "dark:bg-[radial-gradient(ellipse_at_bottom,_#0a192f_0%,_#000000_60%,_#000000_100%)]",
                        "bg-black"
                    )}
                />

                {/* 🌟 Centered content */}
                <div className="relative z-10 flex flex-col items-center justify-center h-full space-y-4 text-center text-white">
                    <img
                        src="/PITON LOGO.png"
                        alt="PITON Logo"
                        className="w-32 h-32 object-contain
                            [filter:drop-shadow(0_0_6px_rgba(250,204,21,0.6))_drop-shadow(0_0_12px_rgba(250,204,21,0.4))_drop-shadow(0_0_20px_rgba(250,204,21,0.2))]"
                    />

                    <h1 className="text-3xl md:text-4xl font-extrabold text-yellow-400 drop-shadow-[0_0_10px_rgba(255,255,0,0.7)] tracking-wide font-sans">
                        Philippine Information Technology of the North
                    </h1>

                    <p className="text-sm md:text-base text-gray-400 italic">
                        — Coding Our Future —
                    </p>
                </div>
            </div>
        </PageLayout>
    );
}
