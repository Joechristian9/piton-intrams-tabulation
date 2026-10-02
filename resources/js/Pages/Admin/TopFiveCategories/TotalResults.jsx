"use client";

import React from "react";
import PageLayout from "@/Layouts/PageLayout";
import TopFiveSelectionTable from "@/Pages/Admin/Partials/TopFiveSelectionTable";

const TotalResults = ({
    categoryName = "Total Combined Scores",
    maleCandidates = [],
    femaleCandidates = [],
    categories = ["face_and_figure", "delivery", "overall_appeal"],
    judgeOrder = [],
}) => {
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
        </PageLayout>
    );
};

export default TotalResults;
