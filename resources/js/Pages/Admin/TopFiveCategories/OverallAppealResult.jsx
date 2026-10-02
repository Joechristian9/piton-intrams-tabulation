"use client";

import React from "react";
import PageLayout from "@/Layouts/PageLayout";
import ResultTable from "@/Pages/Admin/Partials/ResultTable";

const OverallAppealResult = ({
    categoryName = "Overall Appeal",
    maleCandidates = [],
    femaleCandidates = [],
    judgeOrder = [],
}) => {
    return (
        <PageLayout>
            <h2 className="text-white text-xl font-bold mb-4 justify-center flex mt-6">
                {categoryName} Results
            </h2>

            <ResultTable
                title="Female Candidates"
                candidates={femaleCandidates}
                judgeOrder={judgeOrder}
                maxScore={10}
                category={`${categoryName} Female Results`}
            />
            <ResultTable
                title="Male Candidates"
                candidates={maleCandidates}
                judgeOrder={judgeOrder}
                maxScore={10}
                category={`${categoryName} Male Results`}
            />
        </PageLayout>
    );
};

export default OverallAppealResult;
