"use client";

import React, { useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import PageLayout from "@/Layouts/PageLayout";
import { Tabs } from "@/Components/ui/tabs";
import CandidateGrid from "./Partials/CandidateGrid";
import ScoreAlertDialog from "../Partials/ScoreAlertDialog";
import { toast } from "sonner";
import {
    loadDraftScores,
    saveDraftScores,
    clearDraftScores,
} from "@/lib/scoreDrafts";

const DRAFT_CATEGORY = "beauty_face_figure";

const BeautyFaceFigure = ({ candidates }) => {
    const judgeId = usePage().props.auth.user.id;

    const maleCandidates = candidates.filter((c) => c.gender === "male");
    const femaleCandidates = candidates.filter((c) => c.gender === "female");

    const scoresRef = useRef(loadDraftScores(DRAFT_CATEGORY, judgeId));

    const TabContent = ({ candidates }) => {
        const [_, setRerender] = useState(0);
        const [submitted, setSubmitted] = useState(false);

        const handleScoreChange = (candidateId, score) => {
            scoresRef.current[candidateId] = score;
            saveDraftScores(DRAFT_CATEGORY, judgeId, scoresRef.current);
            setRerender((r) => r + 1);
        };

        // Every candidate in this tab already has a saved score: keep it locked
        // even when live updates reload the page with fresh data.
        const alreadySubmitted =
            candidates.length > 0 &&
            candidates.every((c) => c.has_existing_score?.face_and_figure != null);

        const allScoresFilled =
            !alreadySubmitted &&
            candidates.every(
                (c) =>
                    scoresRef.current[c.candidate_id] !== undefined &&
                    scoresRef.current[c.candidate_id] !== ""
            );

        const handleSubmit = () => {
            if (submitted) return;

            const filteredScores = Object.fromEntries(
                candidates.map((c) => [
                    c.candidate_id,
                    scoresRef.current[c.candidate_id] ?? c.existing_score ?? 0,
                ])
            );

            if (!judgeId) {
                toast.error("Judge ID is missing!");
                return;
            }

            if (Object.values(filteredScores).some((s) => s === "")) {
                toast.error("Please fill in all scores before submitting!");
                return;
            }

            router.post(
                route("beauty_face_figure.store"),
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
                        setSubmitted(true);
                        router.reload();
                    },
                    onError: () => {
                        toast.error("Failed to submit scores. Check console.");
                    },
                }
            );
        };

        return (
            <div className="flex flex-col items-center gap-6">
                <CandidateGrid
                    candidates={candidates}
                    maxScore={50}
                    scoresRef={scoresRef}
                    onScoreChange={handleScoreChange}
                    submitted={submitted || alreadySubmitted}
                    categoryField="face_and_figure"
                />

                <ScoreAlertDialog
                    candidates={candidates}
                    scoresRef={scoresRef}
                    allScoresFilled={allScoresFilled}
                    handleSubmit={handleSubmit}
                    categoryField="face_and_figure"
                    submitted={submitted || alreadySubmitted}
                />
            </div>
        );
    };

    const tabs = [
        {
            title: "Female Candidates",
            value: "female",
            category: "Female Beauty of the Face and Figure",
            content: <TabContent candidates={femaleCandidates} />,
        },
        {
            title: "Male Candidates",
            value: "male",
            category: "Male Beauty of the Face and Figure",
            content: <TabContent candidates={maleCandidates} />,
        },
    ];

    return (
        <PageLayout>
            <div className="w-full relative my-10 px-4 flex flex-col items-center">
                <div className="w-full max-w-8xl">
                    <Tabs tabs={tabs} />
                </div>
            </div>
        </PageLayout>
    );
};

export default BeautyFaceFigure;
