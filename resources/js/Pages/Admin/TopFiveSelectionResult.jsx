"use client";

import React, { useState } from "react";
import { router } from "@inertiajs/react";
import PageLayout from "@/Layouts/PageLayout";
import TopFiveSelectionTable from "./Partials/TopFiveSelectionTable";
import TieBreakDialog, {
    FINALIST_COUNT,
    planFinalists,
} from "./Partials/TieBreakDialog";
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

    const saveFinalists = (candidateIds) => {
        const loadingToastId = toast.loading(
            `Saving Top ${FINALIST_COUNT}...`
        );

        router.post(
            route("topFive.set"),
            { candidate_ids: candidateIds },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.dismiss(loadingToastId);
                    toast.success(
                        `Top ${FINALIST_COUNT} Male & Female saved successfully!`
                    );
                    setTiePlan(null);
                },
                onError: (errors) => {
                    toast.dismiss(loadingToastId);
                    toast.error(
                        errors.candidate_ids ??
                            `Failed to save Top ${FINALIST_COUNT}.`
                    );
                },
            }
        );
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
            saveFinalists(
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
                    onConfirm={saveFinalists}
                />
            )}
        </PageLayout>
    );
};

export default TopFiveSelectionResult;
