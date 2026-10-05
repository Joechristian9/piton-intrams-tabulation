import { Lock } from "lucide-react";

// Shown on Round 1 (Top N Selection) pages once the admin has set the finalists:
// the scores are final and the inputs are locked (the server rejects changes too).
export default function RoundClosedNotice({ count = 3 }) {
    return (
        <div
            role="status"
            className="mx-auto mb-6 flex w-full max-w-2xl items-start gap-3 rounded-2xl border border-yellow-400/40 bg-neutral-900 p-4 text-white"
        >
            <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-yellow-400/15 text-yellow-400">
                <Lock className="h-5 w-5" aria-hidden="true" />
            </span>
            <div>
                <p className="text-sm font-semibold text-yellow-300">
                    Top {count} Selection is closed
                </p>
                <p className="mt-1 text-sm text-gray-300">
                    The Top {count} finalists have been set, so these scores can no longer be
                    changed.
                </p>
            </div>
        </div>
    );
}
