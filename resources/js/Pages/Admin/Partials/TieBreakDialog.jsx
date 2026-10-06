"use client";

import React, { useState } from "react";
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogFooter,
} from "@/components/ui/alert-dialog";

export const FINALIST_COUNT = 3;

/**
 * Splits ranked candidates into those certain to advance and, if there's a
 * tie at the cutoff, the tied group the admin must pick from.
 * e.g. ranks 1, 2, 3, 3 -> sure: [1, 2], tied: [3, 3], slots: 1
 */
export const planFinalists = (candidates, count = FINALIST_COUNT) => {
    const countAtOrAbove = (rank) =>
        candidates.filter((c) => c.rank <= rank).length;

    const sure = candidates.filter(
        (c) => countAtOrAbove(c.rank) <= count
    );
    const slots = count - sure.length;

    const nextRank = Math.min(
        ...candidates.filter((c) => !sure.includes(c)).map((c) => c.rank)
    );
    const tied = slots > 0 ? candidates.filter((c) => c.rank === nextRank) : [];

    return { sure, tied, slots };
};

const CandidateRow = ({ c, checked, disabled, onChange }) => (
    <label
        className={`flex items-center gap-3 px-3 py-2 rounded-lg border border-neutral-700 ${
            disabled && !checked ? "opacity-50" : "cursor-pointer hover:bg-neutral-800"
        }`}
    >
        <input
            type="checkbox"
            checked={checked}
            disabled={disabled}
            onChange={onChange}
            className="h-4 w-4 rounded border-neutral-500 bg-neutral-800 text-blue-600"
        />
        <span className="w-8 text-neutral-400">#{c.candidate.candidate_number}</span>
        <span className="flex-1">
            {c.candidate.first_name} {c.candidate.last_name}
        </span>
        <span className="text-sm text-neutral-400">
            Rank {c.rank} · {Number(c.total).toFixed(2)}
        </span>
    </label>
);

const GenderSection = ({ label, plan, picks, setPicks }) => {
    const toggle = (id) =>
        setPicks((p) =>
            p.includes(id) ? p.filter((x) => x !== id) : [...p, id]
        );

    return (
        <div>
            <h3 className="font-semibold mb-2">{label}</h3>

            <div className="flex flex-col gap-2">
                {plan.sure.map((c) => (
                    <CandidateRow key={c.candidate.id} c={c} checked disabled />
                ))}
            </div>

            {plan.slots > 0 && (
                <>
                    <p className="mt-3 mb-2 text-sm text-amber-400">
                        Tie for {plan.slots} remaining{" "}
                        {plan.slots === 1 ? "spot" : "spots"}. Pick{" "}
                        {plan.slots} ({picks.length}/{plan.slots} selected):
                    </p>
                    <div className="flex flex-col gap-2">
                        {plan.tied.map((c) => {
                            const checked = picks.includes(c.candidate.id);
                            return (
                                <CandidateRow
                                    key={c.candidate.id}
                                    c={c}
                                    checked={checked}
                                    disabled={
                                        !checked && picks.length >= plan.slots
                                    }
                                    onChange={() => toggle(c.candidate.id)}
                                />
                            );
                        })}
                    </div>
                </>
            )}
        </div>
    );
};

// sections: [{ label, plan }] — one per group, plan from planFinalists().
const TieBreakDialog = ({ sections, count = FINALIST_COUNT, onCancel, onConfirm }) => {
    const [picks, setPicks] = useState(() => sections.map(() => []));
    const setPicksFor = (index) => (update) =>
        setPicks((all) => all.map((p, i) => (i === index ? update(p) : p)));

    const ready = sections.every((s, i) => picks[i].length === s.plan.slots);

    const confirm = () =>
        onConfirm(
            sections.flatMap((s, i) => [
                ...s.plan.sure.map((c) => c.candidate.id),
                ...picks[i],
            ])
        );

    return (
        <AlertDialog open onOpenChange={(open) => !open && onCancel()}>
            <AlertDialogContent className="sm:max-w-xl max-h-[85vh] flex flex-col bg-neutral-900 text-white rounded-lg shadow-lg p-6">
                <AlertDialogHeader>
                    <AlertDialogTitle>Resolve Tie for Top {count}</AlertDialogTitle>
                    <AlertDialogDescription>
                        Candidates already in the Top {count} are
                        checked. Apply your tie-breaker, then pick who
                        advances from the tied candidates.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="min-h-0 flex-1 overflow-y-auto flex flex-col gap-6 pr-1">
                    {sections.map((s, i) => (
                        <GenderSection
                            key={s.label}
                            label={s.label}
                            plan={s.plan}
                            picks={picks[i]}
                            setPicks={setPicksFor(i)}
                        />
                    ))}
                </div>

                <AlertDialogFooter className="gap-2">
                    <button
                        type="button"
                        onClick={onCancel}
                        className="px-4 py-2 rounded-lg border border-neutral-600 bg-neutral-800 hover:bg-neutral-700 font-semibold"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={confirm}
                        disabled={!ready}
                        className="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Continue
                    </button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
};

export default TieBreakDialog;
