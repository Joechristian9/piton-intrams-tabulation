"use client";

import React, { useEffect, useRef, useState } from "react";
import { Lock } from "lucide-react";
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogFooter,
} from "@/components/ui/alert-dialog";

/**
 * Asks for the admin's password before a sensitive action (start/close/delete).
 * The server checks the password; `error` shows its message and keeps the dialog open.
 */
export default function PasswordConfirmDialog({
    title,
    description,
    confirmLabel,
    danger = false,
    error,
    processing,
    onCancel,
    onConfirm,
}) {
    const [password, setPassword] = useState("");
    const inputRef = useRef(null);

    useEffect(() => {
        if (error) inputRef.current?.select();
    }, [error]);

    const submit = (e) => {
        e.preventDefault();
        if (password && !processing) onConfirm(password);
    };

    return (
        <AlertDialog open onOpenChange={(open) => !open && !processing && onCancel()}>
            <AlertDialogContent className="sm:max-w-md bg-neutral-900 text-white rounded-lg shadow-lg p-6">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <AlertDialogHeader>
                        <AlertDialogTitle>{title}</AlertDialogTitle>
                        {description && <AlertDialogDescription>{description}</AlertDialogDescription>}
                    </AlertDialogHeader>

                    <div>
                        <label htmlFor="confirm-password" className="mb-1 flex items-center gap-2 text-sm font-medium text-gray-300">
                            <Lock className="h-4 w-4 text-yellow-400" aria-hidden="true" />
                            Enter your password to confirm
                        </label>
                        <input
                            ref={inputRef}
                            id="confirm-password"
                            type="password"
                            autoComplete="current-password"
                            autoFocus
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            aria-invalid={Boolean(error)}
                            aria-describedby={error ? "confirm-password-error" : undefined}
                            className="w-full rounded-lg border border-neutral-600 bg-neutral-800 px-3 py-2 text-white focus:border-yellow-400 focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                        />
                        {error && (
                            <p id="confirm-password-error" role="alert" className="mt-1 text-sm text-red-400">
                                {error}
                            </p>
                        )}
                    </div>

                    <AlertDialogFooter className="gap-2">
                        <button
                            type="button"
                            onClick={onCancel}
                            disabled={processing}
                            className="min-h-11 px-4 py-2 rounded-lg border border-neutral-600 bg-neutral-800 hover:bg-neutral-700 font-semibold disabled:opacity-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={!password || processing}
                            className={`min-h-11 px-4 py-2 rounded-lg font-semibold disabled:opacity-50 disabled:cursor-not-allowed ${
                                danger ? "bg-red-600 hover:bg-red-700" : "bg-blue-600 hover:bg-blue-700"
                            }`}
                        >
                            {processing ? "Working…" : confirmLabel}
                        </button>
                    </AlertDialogFooter>
                </form>
            </AlertDialogContent>
        </AlertDialog>
    );
}
