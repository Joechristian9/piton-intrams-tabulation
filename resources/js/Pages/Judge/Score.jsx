"use client";

import React, { useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { toast } from "sonner";
import PageLayout from "@/Layouts/PageLayout";
import { Tabs } from "@/Components/ui/tabs";
import CandidateGrid from "@/Pages/Judge/Partials/CandidateGrid";
import ScoreAlertDialog from "@/Pages/Judge/Partials/ScoreAlertDialog";
import RoundClosedNotice from "@/Pages/Judge/Partials/RoundClosedNotice";
import {
    loadDraftScores,
    saveDraftScores,
    clearDraftScores,
} from "@/lib/scoreDrafts";

// One group's candidates for this category. Defined outside the page so live
// reloads update it in place instead of remounting every card.
function GroupTab({ candidates, category, judgeId, draftKey, scoresRef, roundClosed }) {
    const [, setRerender] = useState(0);
    const [submitted, setSubmitted] = useState(false);

    const handleScoreChange = (candidateId, score) => {
        scoresRef.current = { ...scoresRef.current, [candidateId]: score };
        saveDraftScores(draftKey, judgeId, scoresRef.current);
        setRerender((r) => r + 1);
    };

    // Every candidate here already has a saved score: keep the tab locked.
    const alreadySubmitted =
        candidates.length > 0 && candidates.every((c) => c.existing_score != null);

    const filled = (c) => scoresRef.current[c.id] !== undefined && scoresRef.current[c.id] !== "";
    const allScoresFilled =
        !alreadySubmitted && !roundClosed && candidates.length > 0 && candidates.every(filled);

    const handleSubmit = () => {
        const scores = Object.fromEntries(candidates.map((c) => [c.id, scoresRef.current[c.id]]));

        router.post(
            route("score.store", category.id),
            { scores },
            {
                preserveScroll: true,
                onSuccess: () => {
                    clearDraftScores(draftKey, judgeId, Object.keys(scores));
                    toast.success("Scores submitted successfully!");
                    setSubmitted(true);
                },
                onError: (errors) => {
                    // e.g. the event was closed or the Top N set while submitting.
                    toast.error(errors.scores ?? Object.values(errors)[0] ?? "Failed to submit scores.");
                },
            }
        );
    };

    const locked = submitted || alreadySubmitted || roundClosed;

    return (
        <div className="flex flex-col items-center gap-6">
            <CandidateGrid
                candidates={candidates}
                maxScore={category.max_score}
                scoresRef={scoresRef}
                onScoreChange={handleScoreChange}
                submitted={locked}
            />
            <ScoreAlertDialog
                candidates={candidates}
                scoresRef={scoresRef}
                allScoresFilled={allScoresFilled}
                handleSubmit={handleSubmit}
                submitted={locked}
            />
        </div>
    );
}

export default function Score({ category, groups, roundClosed, finalistsPerGroup }) {
    const judgeId = usePage().props.auth.user.id;
    const draftKey = `cat${category.id}`;
    const scoresRef = useRef(loadDraftScores(draftKey, judgeId));

    const tabs = groups.map((group) => ({
        title: `${group.name} Candidates`,
        value: String(group.id),
        category: `${group.name} ${category.name}`,
        content: (
            <GroupTab
                candidates={group.candidates}
                category={category}
                judgeId={judgeId}
                draftKey={draftKey}
                scoresRef={scoresRef}
                roundClosed={roundClosed}
            />
        ),
    }));

    return (
        <PageLayout>
            <div className="relative my-10 flex w-full flex-col items-center px-4">
                <div className="w-full max-w-8xl">
                    {roundClosed && <RoundClosedNotice count={finalistsPerGroup} />}
                    {tabs.length > 0 ? (
                        <Tabs tabs={tabs} />
                    ) : (
                        <p className="text-center text-gray-300">No candidates in this category yet.</p>
                    )}
                </div>
            </div>
        </PageLayout>
    );
}
