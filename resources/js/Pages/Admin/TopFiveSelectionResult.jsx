"use client";

import React, { useState } from "react";
import { router } from "@inertiajs/react";
import PageLayout from "@/Layouts/PageLayout";
import TopFiveSelectionTable from "./Partials/TopFiveSelectionTable";
import TieBreakDialog, {
    FINALIST_COUNT,
    planFinalists,
} from "./Partials/TieBreakDialog";
import ConfirmFinalistsDialog from "./Partials/ConfirmFinalistsDialog";
import { HoverBorderGradient } from "@/Components/ui/hover-border-gradient";
import { toast } from "sonner";

const TopFiveSelectionResult = ({
    categoryName = "Top Three Selection Results",
    maleCandidates,
    femaleCandidates,
    categories,
    judgeOrder = [],
}) => {
    // Set when there's a tie at the cutoff and the admin needs to pick.
    const [tiePlan, setTiePlan] = useState(null);
    // The finalists picked, waiting for the admin's password in the confirm dialog.
    const [pendingIds, setPendingIds] = useState(null);
    const [passwordError, setPasswordError] = useState(null);
    const [processing, setProcessing] = useState(false);

    const askToConfirm = (candidateIds) => {
        setTiePlan(null);
        setPasswordError(null);
        setPendingIds(candidateIds);
    };

    const closeConfirm = () => {
        setPendingIds(null);
        setPasswordError(null);
    };

    const saveFinalists = (password) => {
        setProcessing(true);
        setPasswordError(null);

        router.post(
            route("topFive.set"),
            { candidate_ids: pendingIds, password },
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeConfirm();
                    toast.success(
                        `Top ${FINALIST_COUNT} Male & Female saved successfully!`
                    );
                },
                onError: (errors) => {
                    // Wrong password: keep the dialog open so the admin can retry.
                    if (errors.password) {
                        setPasswordError(errors.password);
                        return;
                    }
                    closeConfirm();
                    toast.error(
                        errors.candidate_ids ??
                            `Failed to save Top ${FINALIST_COUNT}.`
                    );
                },
                onFinish: () => setProcessing(false),
            }
        );
    };

    // Ranked rows of the pending finalists, for the confirm dialog.
    const pendingFinalists = pendingIds && {
        female: femaleCandidates.filter((c) => pendingIds.includes(c.candidate.id)),
        male: maleCandidates.filter((c) => pendingIds.includes(c.candidate.id)),
    };

    const handleSetTopThree = () => {
        const plan = {
            female: planFinalists(femaleCandidates),
            male: planFinalists(maleCandidates),
        };

        for (const [gender, p] of Object.entries(plan)) {
            if (p.slots > p.tied.length) {
                toast.error(
                    `Not enough ${gender} candidates for a Top ${FINALIST_COUNT}.`
                );
                return;
            }
        }

        if (plan.female.slots === 0 && plan.male.slots === 0) {
            askToConfirm(
                [...plan.female.sure, ...plan.male.sure].map(
                    (c) => c.candidate.id
                )
            );
        } else {
            setTiePlan(plan);
        }
    };

    return (
        <PageLayout>
            <h2 className="text-white text-xl font-bold mb-4 justify-center flex mt-6">
                {categoryName}
            </h2>

            {/* Female Table */}
            <TopFiveSelectionTable
                title="Female Candidates"
                candidates={femaleCandidates}
                categories={categories}
                judges={judgeOrder}
                category={`${categoryName} Female Results`}
            />

            {/* Male Table */}
            <TopFiveSelectionTable
                title="Male Candidates"
                candidates={maleCandidates}
                categories={categories}
                judges={judgeOrder}
                category={`${categoryName} Male Results`}
            />

            <div className="flex justify-center mb-10">
                <HoverBorderGradient
                    containerClassName="rounded-full"
                    as="button"
                    className="dark:bg-neutral-800 bg-white text-black dark:text-neutral-100 flex items-center space-x-2 px-12 py-1 text-lg font-semibold"
                    onClick={handleSetTopThree}
                >
                    <span>Set Top 3 (Male & Female)</span>
                </HoverBorderGradient>
            </div>

            {tiePlan && (
                <TieBreakDialog
                    plan={tiePlan}
                    onCancel={() => setTiePlan(null)}
                    onConfirm={askToConfirm}
                />
            )}

            {pendingFinalists && (
                <ConfirmFinalistsDialog
                    finalists={pendingFinalists}
                    error={passwordError}
                    processing={processing}
                    onCancel={closeConfirm}
                    onConfirm={saveFinalists}
                />
            )}
        </PageLayout>
    );
};

export default TopFiveSelectionResult;
