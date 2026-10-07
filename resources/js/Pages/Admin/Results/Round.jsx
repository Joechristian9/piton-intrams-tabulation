import React, { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import PageLayout from "@/Layouts/PageLayout";
import TopFiveSelectionTable from "@/Pages/Admin/Partials/TopFiveSelectionTable";
import TieBreakDialog, { planFinalists } from "@/Pages/Admin/Partials/TieBreakDialog";
import ConfirmFinalistsDialog from "@/Pages/Admin/Partials/ConfirmFinalistsDialog";
import { HoverBorderGradient } from "@/Components/ui/hover-border-gradient";

// Round 1 totals per group, and setting the Top N (tie-break, then password).
export default function Round({ event, categories, judges, groups }) {
    const n = event.finalists_per_group;
    const [tieSections, setTieSections] = useState(null);
    const [pendingIds, setPendingIds] = useState(null);
    const [passwordError, setPasswordError] = useState(null);
    const [processing, setProcessing] = useState(false);

    const askToConfirm = (ids) => {
        setTieSections(null);
        setPasswordError(null);
        setPendingIds(ids);
    };

    const closeConfirm = () => {
        setPendingIds(null);
        setPasswordError(null);
    };

    const handleSetTop = () => {
        const sections = groups.map((g) => ({ label: g.name, plan: planFinalists(g.rows, n) }));

        const short = sections.find((s) => s.plan.slots > s.plan.tied.length);
        if (short) {
            toast.error(`Not enough ${short.label} candidates for a Top ${n}.`);
            return;
        }

        if (sections.every((s) => s.plan.slots === 0)) {
            askToConfirm(sections.flatMap((s) => s.plan.sure.map((c) => c.candidate.id)));
        } else {
            setTieSections(sections);
        }
    };

    const save = (password) => {
        setProcessing(true);
        setPasswordError(null);

        router.post(
            route("admin.finalists.set", event.id),
            { candidate_ids: pendingIds, password },
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeConfirm();
                    toast.success(`Top ${n} saved.`);
                },
                onError: (errors) => {
                    // Wrong password: keep the dialog open so the admin can retry.
                    if (errors.password) {
                        setPasswordError(errors.password);
                        return;
                    }
                    closeConfirm();
                    toast.error(errors.candidate_ids ?? `Failed to save Top ${n}.`);
                },
                onFinish: () => setProcessing(false),
            }
        );
    };

    const pendingGroups =
        pendingIds &&
        groups.map((g) => ({
            label: g.name,
            finalists: g.rows.filter((r) => pendingIds.includes(r.candidate.id)),
        }));

    return (
        <>
            <h2 className="mt-6 mb-1 flex justify-center text-xl font-bold text-white">
                Top {n} Selection Results
            </h2>
            <p className="mb-4 text-center text-sm text-gray-400">{event.name}</p>

            {groups.map((group) => (
                <TopFiveSelectionTable
                    key={group.id}
                    title={`${group.name} Candidates`}
                    candidates={group.rows}
                    categories={categories}
                    judges={judges}
                    highlight={n}
                    category={`${event.name} — Top ${n} Selection ${group.name} Results`}
                />
            ))}

            <div className="mb-10 flex justify-center">
                <HoverBorderGradient
                    containerClassName="rounded-full"
                    as="button"
                    className="flex items-center space-x-2 bg-white px-12 py-1 text-lg font-semibold text-black dark:bg-neutral-800 dark:text-neutral-100"
                    onClick={handleSetTop}
                >
                    <span>
                        {event.finalistsSet ? `Change Top ${n}` : `Set Top ${n}`} ({groups.map((g) => g.name).join(" & ")})
                    </span>
                </HoverBorderGradient>
            </div>

            {tieSections && (
                <TieBreakDialog
                    sections={tieSections}
                    count={n}
                    onCancel={() => setTieSections(null)}
                    onConfirm={askToConfirm}
                />
            )}

            {pendingGroups && (
                <ConfirmFinalistsDialog
                    groups={pendingGroups}
                    count={n}
                    error={passwordError}
                    processing={processing}
                    onCancel={closeConfirm}
                    onConfirm={save}
                />
            )}
        </>
    );
}

// Persistent layout: the sidebar and live-update pollers stay mounted between pages
// (no remount, no extra poll request per click).
Round.layout = (page) => <PageLayout>{page}</PageLayout>;
