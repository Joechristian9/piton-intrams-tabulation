"use client";

import React from "react";
import ScoreInput from "./ScoreInput";

const CandidateGrid = ({
    candidates,
    maxScore = 10,
    scoresRef,
    onScoreChange,
    submitted = false,
    categoryField, // <-- new prop
}) => {
    return (
        <div className="w-full p-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 justify-center">
            {candidates.map((candidate, index) => {
                const defaultImage = "/candidates/linear.jfif";
                const genderFolder =
                    candidate.gender?.toLowerCase() || "unknown";
                const imageSrc =
                    candidate.profile_img ||
                    `/candidates/${genderFolder}/${genderFolder}_${
                        index + 1
                    }.jpg`;

                const candidateKey = candidate.candidate_id;

                // Use scoresRef first, otherwise use the existing score from backend
                const currentScore =
                    scoresRef.current[candidateKey] ??
                    candidate[categoryField] ?? // <-- this shows existing score
                    "";

                const handleScoreChange = (val) => {
                    onScoreChange(candidateKey, val);
                };

                return (
                    <div
                        key={candidateKey}
                        className="bg-neutral-900 border border-white/20 rounded-xl p-4 shadow-[0_4px_15px_rgba(255,255,255,0.3)] hover:shadow-[0_6px_25px_rgba(255,255,255,0.5)] transition-shadow duration-300 flex flex-col items-center gap-3 overflow-hidden"
                    >
                        <img
                            src={imageSrc || defaultImage}
                            alt={`${candidate.first_name} ${candidate.last_name}`}
                            className="w-full h-72 object-cover rounded-md"
                        />

                        <div className="text-center w-full overflow-hidden">
                            <p className="text-xs text-gray-400 mb-1">
                                # {candidate.candidate_number}
                            </p>
                            <h3 className="font-bold text-white truncate w-full px-2">
                                {candidate.first_name} {candidate.last_name}
                            </h3>

                            {candidate.course && (
                                <p
                                    className="text-sm text-gray-300 mt-1 whitespace-nowrap overflow-hidden text-ellipsis w-full px-2"
                                    title={candidate.course}
                                >
                                    {candidate.course}
                                </p>
                            )}
                        </div>

                        <ScoreInput
                            value={currentScore}
                            onChange={handleScoreChange}
                            max={maxScore}
                            // Disable only if submitted OR this category already has a score
                            disabled={
                                submitted ||
                                candidate.has_existing_score?.[categoryField] !=
                                    null
                            }
                        />
                    </div>
                );
            })}
        </div>
    );
};

export default CandidateGrid;
