"use client";

import React, { memo, useCallback } from "react";
import candidateName from "@/lib/candidateName";
import ScoreInput from "./ScoreInput";
import CandidatePhoto from "@/Components/CandidatePhoto";

// One candidate's card. Memoized: typing a score re-renders only the card whose
// value changed, not the whole grid (judges' phones have to repaint less).
const CandidateCard = memo(function CandidateCard({ candidate, value, maxScore, disabled, onScoreChange, priority }) {
    const handleChange = useCallback((val) => onScoreChange(candidate.id, val), [onScoreChange, candidate.id]);

    return (
        <div className="bg-neutral-900 border border-white/20 rounded-xl p-4 shadow-[0_4px_15px_rgba(255,255,255,0.3)] hover:shadow-[0_6px_25px_rgba(255,255,255,0.5)] transition-shadow duration-300 flex flex-col items-center gap-3 overflow-hidden">
            <CandidatePhoto
                path={candidate.profile_img}
                alt={candidateName(candidate)}
                width={360}
                height={450}
                priority={priority}
                className="w-full h-72 object-cover rounded-md"
            />

            <div className="text-center w-full overflow-hidden">
                <p className="text-xs text-gray-400 mb-1"># {candidate.candidate_number}</p>
                <h3 className="font-bold text-white truncate w-full px-2">
                    {candidateName(candidate)}
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

            <ScoreInput value={value} onChange={handleChange} max={maxScore} disabled={disabled} />
        </div>
    );
});

const CandidateGrid = ({ candidates, maxScore = 10, scoresRef, onScoreChange, submitted = false }) => (
    <div className="w-full p-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 justify-center">
        {candidates.map((candidate, i) => (
            <CandidateCard
                key={candidate.id}
                candidate={candidate}
                // The first two cards are on screen as the page opens (one column on phones).
                priority={i < 2}
                // Typed draft first, then the saved score.
                value={scoresRef.current[candidate.id] ?? candidate.existing_score ?? ""}
                maxScore={maxScore}
                disabled={submitted || candidate.existing_score != null}
                onScoreChange={onScoreChange}
            />
        ))}
    </div>
);

export default CandidateGrid;
