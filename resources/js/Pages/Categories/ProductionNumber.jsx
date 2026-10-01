"use client";

import React, { useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import PageLayout from "@/Layouts/PageLayout";
import { Tabs } from "@/Components/ui/tabs";
import CandidateGrid from "./Partials/CandidateGrid";
import ScoreAlertDialog from "./Partials/ScoreAlertDialog";
import { toast } from "sonner";

const ProductionNumber = ({ candidates }) => {
    const judgeId = usePage().props.auth.user.id;
    const maleCandidates = candidates.filter((c) => c.gender === "male");
    const femaleCandidates = candidates.filter((c) => c.gender === "female");

    const scoresRef = useRef({});

    const TabContent = ({ candidates }) => {
        const [_, setRerender] = useState(0);
        const [submitted, setSubmitted] = useState(false);

        const handleScoreChange = (candidateId, score) => {
            scoresRef.current = { ...scoresRef.current, [candidateId]: score };
            setRerender((r) => r + 1);
        };

        const allScoresFilled = candidates.every(
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
                        toast.success("Scores submitted successfully!");
                        router.reload();
                        setSubmitted(true);
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
                    maxScore={10}
                    scoresRef={scoresRef}
                    onScoreChange={handleScoreChange}
                    submitted={submitted}
                />

                <ScoreAlertDialog
                    candidates={candidates}
                    scoresRef={scoresRef}
                    allScoresFilled={allScoresFilled}
                    handleSubmit={handleSubmit}
                    submitted={submitted}
                />
            </div>
        );
    };

    const tabs = [
        {
            title: "Male Candidates",
            value: "male",
            category: "Male Production Number",
            content: <TabContent candidates={maleCandidates} />,
        },
        {
            title: "Female Candidates",
            value: "female",
            category: "Female Production Number",
            content: <TabContent candidates={femaleCandidates} />,
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

export default ProductionNumber;
