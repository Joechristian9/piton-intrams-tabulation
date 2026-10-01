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
                title="Male Candidates"
                candidates={maleCandidates}
                judgeOrder={judgeOrder}
                category={`${categoryName} Male Results`}
            />
            <ResultTable
                title="Female Candidates"
                candidates={femaleCandidates}
                judgeOrder={judgeOrder}
                category={`${categoryName} Female Results`}
            />
        </PageLayout>
    );
};

export default OverallAppealResult;
