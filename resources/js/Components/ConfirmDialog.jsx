"use client";

import React from "react";
import { AlertTriangle } from "lucide-react";
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogFooter,
} from "@/components/ui/alert-dialog";

/**
 * Asks "are you sure?" before a destructive action. Stays open while `processing`;
 * `error` shows why the action failed so the admin can retry or cancel.
 */
export default function ConfirmDialog({
    title,
    description,
    confirmLabel = "Delete",
    error,
    processing = false,
    onCancel,
    onConfirm,
}) {
    return (
        <AlertDialog open onOpenChange={(open) => !open && !processing && onCancel()}>
            <AlertDialogContent className="sm:max-w-md bg-neutral-900 text-white rounded-lg shadow-lg p-6">
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5 shrink-0 text-red-400" aria-hidden="true" />
                        {title}
                    </AlertDialogTitle>
                    {description && <AlertDialogDescription className="text-gray-300">{description}</AlertDialogDescription>}
                </AlertDialogHeader>

                {error && (
                    <p role="alert" className="rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-300">
                        {error}
                    </p>
                )}

                <AlertDialogFooter className="gap-2">
                    <button
                        type="button"
                        autoFocus
                        onClick={onCancel}
                        disabled={processing}
                        className="min-h-11 px-4 py-2 rounded-lg border border-neutral-600 bg-neutral-800 hover:bg-neutral-700 font-semibold disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={processing}
                        className="min-h-11 px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {processing ? "Deleting…" : confirmLabel}
                    </button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
