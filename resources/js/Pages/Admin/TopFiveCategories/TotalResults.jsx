"use client";

import React from "react";
import PageLayout from "@/Layouts/PageLayout";
import TopFiveSelectionTable from "@/Pages/Admin/Partials/TopFiveSelectionTable";

const TotalResults = ({
    categoryName = "Total Combined Scores",
    maleCandidates = [],
    femaleCandidates = [],
    categories = ["face_and_figure", "delivery", "overall_appeal"],
}) => {
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
        </PageLayout>
    );
};

export default TotalResults;
