import React from "react";
import { Head } from "@inertiajs/react";

// One slip per judge to cut out and hand over: event, name, login page, username, password.
export default function JudgeSlips({ event, judges }) {
    const loginUrl = `${window.location.origin}/login`;

    return (
        <div className="min-h-screen bg-white p-6 text-black">
            <Head title={`${event.name} — judge slips`} />
            <div className="mb-6 flex items-center justify-between print:hidden">
                <h1 className="text-xl font-bold">{event.name}: judge credential slips</h1>
                <button type="button" onClick={() => window.print()} className="min-h-11 rounded-lg bg-black px-4 font-semibold text-white">
                    Print
                </button>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 print:grid-cols-2">
                {judges.map((j) => (
                    <div key={j.id} className="break-inside-avoid rounded-lg border-2 border-dashed border-gray-400 p-4">
                        <p className="text-xs uppercase tracking-wider text-gray-600">{event.name}</p>
                        <p className="mt-1 text-lg font-bold">{j.name}</p>
                        <p className="mt-2 text-sm text-gray-700">Log in at {loginUrl}</p>
                        <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 font-mono text-base">
                            <dt className="text-gray-600">Username</dt>
                            <dd className="font-semibold">{j.username}</dd>
                            <dt className="text-gray-600">Password</dt>
                            <dd className="font-semibold">{j.password ?? "(changed by the judge)"}</dd>
                        </dl>
                    </div>
                ))}
            </div>
        </div>
    );
}
