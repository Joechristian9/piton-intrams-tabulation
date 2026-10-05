"use client";

import React, { useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import PageLayout from "@/Layouts/PageLayout";
import { Tabs } from "@/Components/ui/tabs";
import CandidateGrid from "./Partials/CandidateGrid";
import ScoreAlertDialog from "./Partials/ScoreAlertDialog";
import RoundClosedNotice from "./Partials/RoundClosedNotice";
import { toast } from "sonner";
import {
    loadDraftScores,
    saveDraftScores,
    clearDraftScores,
} from "@/lib/scoreDrafts";

const DRAFT_CATEGORY = "production_number";

const ProductionNumber = ({ candidates }) => {
    const { props } = usePage();
    const judgeId = props.auth.user.id;
    // Round 1 closes once the admin sets the Top 3: these scores can no longer change.
    const roundClosed = Boolean(props.finalistsSet);
    const maleCandidates = candidates.filter((c) => c.gender === "male");
    const femaleCandidates = candidates.filter((c) => c.gender === "female");

    const scoresRef = useRef(loadDraftScores(DRAFT_CATEGORY, judgeId));

    const TabContent = ({ candidates }) => {
        const [_, setRerender] = useState(0);
        const [submitted, setSubmitted] = useState(false);

        const handleScoreChange = (candidateId, score) => {
            scoresRef.current = { ...scoresRef.current, [candidateId]: score };
            saveDraftScores(DRAFT_CATEGORY, judgeId, scoresRef.current);
            setRerender((r) => r + 1);
        };

        // Every candidate in this tab already has a saved score: keep it locked
        // even when live updates reload the page with fresh data.
        const alreadySubmitted =
            candidates.length > 0 &&
            candidates.every((c) => c.existing_score != null && c.existing_score !== "");

        const allScoresFilled =
            !alreadySubmitted &&
            !roundClosed &&
            candidates.every(
                (c) =>
                    scoresRef.current[c.id] !== undefined &&
                    scoresRef.current[c.id] !== ""
            );

        const handleSubmit = () => {
            const filteredScores = Object.fromEntries(
                candidates
                    .map((c) => [c.id, scoresRef.current[c.id]])
                    .filter(([_, score]) => score !== undefined && score !== "")
            );

            if (!judgeId) {
                alert("Judge ID is missing!");
                return;
            }

            if (Object.keys(filteredScores).length !== candidates.length) {
                alert("Please fill in all scores before submitting!");
                return;
            }

            router.post(
                route("production_number.store"),
                {
                    judge_id: judgeId,
                    scores: filteredScores,
                },
                {
                    onSuccess: () => {
                        clearDraftScores(
                            DRAFT_CATEGORY,
                            judgeId,
                            Object.keys(filteredScores)
                        );
                        toast.success("Scores submitted successfully!");
                        router.reload();
                        setSubmitted(true);
                    },
                    onError: (errors) => {
                        // e.g. the admin set the Top 3 while this judge was submitting.
                        toast.error(errors.scores ?? "Failed to submit scores.");
                    },
                }
            );
        };

        return (
            <div className="flex flex-col items-center gap-6">
                <CandidateGrid
                    candidates={candidates}
                    maxScore={10}
                    scoresRef={scoresRef}
                    onScoreChange={handleScoreChange}
                    submitted={submitted || alreadySubmitted || roundClosed}
                />

                <ScoreAlertDialog
                    candidates={candidates}
                    scoresRef={scoresRef}
                    allScoresFilled={allScoresFilled}
                    handleSubmit={handleSubmit}
                    submitted={submitted || alreadySubmitted || roundClosed}
                />
            </div>
        );
    };

    const tabs = [
        {
            title: "Female Candidates",
            value: "female",
            category: "Female Production Number",
            content: <TabContent candidates={femaleCandidates} />,
        },
        {
            title: "Male Candidates",
            value: "male",
            category: "Male Production Number",
            content: <TabContent candidates={maleCandidates} />,
        },
    ];

    return (
        <PageLayout>
            <div className="w-full relative my-10 px-4 flex flex-col items-center">
                <div className="w-full max-w-8xl">
                    {roundClosed && <RoundClosedNotice />}
                    <Tabs tabs={tabs} />
                </div>
            </div>
        </PageLayout>
    );
};

export default ProductionNumber;
