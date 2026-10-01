"use client";

import React from "react";
import { router } from "@inertiajs/react";
import PageLayout from "@/Layouts/PageLayout";
import TopFiveSelectionTable from "./Partials/TopFiveSelectionTable";
import { HoverBorderGradient } from "@/Components/ui/hover-border-gradient";
import { toast } from "sonner";

const TopFiveSelectionResult = ({
    categoryName = "Top Three Selection Results",
    maleCandidates,
    femaleCandidates,
    categories,
}) => {
    const handleSetTopFive = () => {
        const maleTop5 = maleCandidates
            .filter((c) => c.rank <= 3)
            .map((c) => c.candidate.id);

        const femaleTop5 = femaleCandidates
            .filter((c) => c.rank <= 3)
            .map((c) => c.candidate.id);

        const allTop5 = [...maleTop5, ...femaleTop5];

        if (maleTop5.length !== 3 || femaleTop5.length !== 3) {
            toast.error(
                "There must be exactly 5 male and 5 female top-ranked candidates."
            );
            return;
        }

        const loadingToastId = toast.loading("Saving Top 5...");

        router.post(
            route("topFive.set"),
            { candidate_ids: allTop5 },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.dismiss(loadingToastId);
                    toast.success("Top 5 Male & Female saved successfully!");
                },
                onError: () => {
                    toast.dismiss(loadingToastId);
                    toast.error("Failed to save Top 5.");
                },
            }
        );
    };

    return (
        <PageLayout>
            <h2 className="text-white text-xl font-bold mb-4 justify-center flex mt-6">
                {categoryName}
            </h2>

            {/* Male Table */}
            <TopFiveSelectionTable
                title="Male Candidates"
                candidates={maleCandidates}
                categories={categories}
                category={`${categoryName} Male Results`}
            />

            {/* Female Table */}
            <TopFiveSelectionTable
                title="Female Candidates"
                candidates={femaleCandidates}
                categories={categories}
                category={`${categoryName} Female Results`}
            />

            <div className="flex justify-center mb-10">
                <HoverBorderGradient
                    containerClassName="rounded-full"
                    as="button"
                    className="dark:bg-neutral-800 bg-white text-black dark:text-neutral-100 flex items-center space-x-2 px-12 py-1 text-lg font-semibold"
                    onClick={handleSetTopFive}
                >
                    <span>Set Top 3 (Male & Female)</span>
                </HoverBorderGradient>
            </div>
        </PageLayout>
    );
};

export default TopFiveSelectionResult;
