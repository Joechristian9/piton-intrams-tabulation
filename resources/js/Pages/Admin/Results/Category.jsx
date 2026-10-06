import React from "react";
import PageLayout from "@/Layouts/PageLayout";
import ResultTable from "@/Pages/Admin/Partials/ResultTable";

// One category of an event: each judge's score and the average, per group.
export default function Category({ event, category, judges, groups }) {
    return (
        <PageLayout>
            <h2 className="mt-6 mb-1 flex justify-center text-xl font-bold text-white">
                {category.name} Results
            </h2>
            <p className="mb-4 text-center text-sm text-gray-400">{event.name}</p>

            {groups.map((group) => (
                <ResultTable
                    key={group.id}
                    title={`${group.name} Candidates`}
                    candidates={group.rows}
                    judgeOrder={judges}
                    maxScore={category.max_score}
                    category={`${event.name} — ${category.name} ${group.name} Results`}
                />
            ))}
        </PageLayout>
    );
}
