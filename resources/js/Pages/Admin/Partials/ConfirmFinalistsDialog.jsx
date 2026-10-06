"use client";

import React, { useEffect, useRef, useState } from "react";
import candidateName from "@/lib/candidateName";
import { Lock } from "lucide-react";
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogFooter,
} from "@/components/ui/alert-dialog";
import { FINALIST_COUNT } from "./TieBreakDialog";

const FinalistList = ({ label, finalists }) => (
    <div>
        <h3 className="mb-2 font-semibold">{label}</h3>
        <ul className="flex flex-col gap-2">
            {finalists.map((c) => (
                <li
                    key={c.candidate.id}
                    className="flex items-center gap-3 rounded-lg border border-neutral-700 px-3 py-2"
                >
                    <span className="w-8 text-neutral-400">
                        #{c.candidate.candidate_number}
                    </span>
                    <span className="flex-1">
                        {candidateName(c.candidate)}
                    </span>
                    <span className="text-sm text-neutral-400">
                        Rank {c.rank} · {Number(c.total).toFixed(2)}
                    </span>
                </li>
            ))}
        </ul>
    </div>
);

/**
 * Last step before saving the Top 3: shows who advances, warns that Round 1 locks,
 * and asks for the admin's password (checked by the server).
 */
// groups: [{ label, finalists }] — finalists are ranked result rows.
const ConfirmFinalistsDialog = ({
    groups,
    count = FINALIST_COUNT,
    error,
    processing,
    onCancel,
    onConfirm,
}) => {
    const [password, setPassword] = useState("");
    const inputRef = useRef(null);

    // A wrong password: select it so the admin can retype straight away.
    useEffect(() => {
        if (error) inputRef.current?.select();
    }, [error]);

    const submit = (e) => {
        e.preventDefault();
        if (password && !processing) onConfirm(password);
    };

    return (
        <AlertDialog open onOpenChange={(open) => !open && !processing && onCancel()}>
            <AlertDialogContent className="sm:max-w-xl max-h-[85vh] flex flex-col bg-neutral-900 text-white rounded-lg shadow-lg p-6">
                <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col gap-4">
                    <AlertDialogHeader>
                        <AlertDialogTitle>Confirm Top {count}</AlertDialogTitle>
                        <AlertDialogDescription>
                            These candidates advance to the finals. Once saved, Top{" "}
                            {count} Selection scores are locked and judges can
                            no longer change them.
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    <div className="min-h-0 flex-1 overflow-y-auto flex flex-col gap-6 pr-1">
                        {groups.map((g) => (
                            <FinalistList key={g.label} label={g.label} finalists={g.finalists} />
                        ))}
                    </div>

                    <div>
                        <label
                            htmlFor="confirm-finalists-password"
                            className="mb-1 flex items-center gap-2 text-sm font-medium text-gray-300"
                        >
                            <Lock className="h-4 w-4 text-yellow-400" aria-hidden="true" />
                            Enter your password to confirm
                        </label>
                        <input
                            ref={inputRef}
                            id="confirm-finalists-password"
                            type="password"
                            autoComplete="current-password"
                            autoFocus
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            aria-invalid={Boolean(error)}
                            aria-describedby={error ? "confirm-finalists-error" : undefined}
                            className="w-full rounded-lg border border-neutral-600 bg-neutral-800 px-3 py-2 text-white focus:border-yellow-400 focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                        />
                        {error && (
                            <p
                                id="confirm-finalists-error"
                                role="alert"
                                className="mt-1 text-sm text-red-400"
                            >
                                {error}
                            </p>
                        )}
                    </div>

                    <AlertDialogFooter className="gap-2">
                        <button
                            type="button"
                            onClick={onCancel}
                            disabled={processing}
                            className="px-4 py-2 rounded-lg border border-neutral-600 bg-neutral-800 hover:bg-neutral-700 font-semibold disabled:opacity-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={!password || processing}
                            className="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {processing ? "Saving…" : `Confirm Top ${count}`}
                        </button>
                    </AlertDialogFooter>
                </form>
            </AlertDialogContent>
        </AlertDialog>
    );
};

export default ConfirmFinalistsDialog;
