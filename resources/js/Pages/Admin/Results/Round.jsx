import React from "react";
import PageLayout from "@/Layouts/PageLayout";
import TopFiveSelectionTable from "@/Pages/Admin/Partials/TopFiveSelectionTable";

// Round 1 totals per group (each category's average and the round total).
export default function Round({ event, categories, judges, groups }) {
    const n = event.finalists_per_group;

    return (
        <PageLayout>
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
        </PageLayout>
    );
}
