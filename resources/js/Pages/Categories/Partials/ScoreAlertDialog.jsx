"use client";

import React from "react";
import { HoverBorderGradient } from "@/Components/ui/hover-border-gradient";
import {
    AlertDialog,
    AlertDialogTrigger,
    AlertDialogContent,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogCancel,
    AlertDialogAction,
} from "@/components/ui/alert-dialog";

const ScoreAlertDialog = ({
    candidates,
    scoresRef,
    allScoresFilled,
    handleSubmit,
    submitted = false,
}) => {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <HoverBorderGradient
                    as="button"
                    containerClassName="rounded-full"
                    className={`dark:bg-neutral-800 bg-white text-black dark:text-neutral-100 flex items-center space-x-2 px-6 py-2 text-lg font-semibold ${
                        !allScoresFilled || submitted
                            ? "opacity-50 cursor-not-allowed"
                            : ""
                    }`}
                    disabled={!allScoresFilled || submitted}
                >
                    Submit Scores
                </HoverBorderGradient>
            </AlertDialogTrigger>

            <AlertDialogContent className="sm:max-w-lg max-h-[75vh] bg-neutral-900 text-white rounded-lg shadow-lg p-6">
                <AlertDialogHeader>
                    <AlertDialogTitle>Verify Scores</AlertDialogTitle>
                    <AlertDialogDescription>
                        Please review the scores below before submitting.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="mt-4">
                    <table className="w-full text-left border-collapse border border-gray-700">
                        <thead className="bg-neutral-800 text-white">
                            <tr>
                                <th className="p-2 border-b border-gray-600">
                                    #
                                </th>
                                <th className="p-2 border-b border-gray-600">
                                    Candidate
                                </th>
                                <th className="p-2 border-b border-gray-600 text-center">
                                    Score
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {candidates.map((c, idx) => (
                                <tr key={c.id} className="bg-neutral-800">
                                    <td className="p-2 border-b border-gray-600">
                                        {c.candidate_number}
                                    </td>
                                    <td className="p-2 border-b border-gray-600">
                                        <div className="flex items-center gap-2">
                                            <img
                                                src={
                                                    c.profile_img ||
                                                    "/default-avatar.png"
                                                }
                                                alt={`${c.first_name} ${c.last_name}`}
                                                className="w-6 h-6 rounded-full object-cover"
                                            />
                                            <span>
                                                {c.first_name} {c.last_name}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="p-2 border-b border-gray-600 text-center">
                                        {scoresRef.current[c.id]}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <AlertDialogFooter className="mt-4 flex justify-end gap-2">
                    <AlertDialogCancel asChild>
                        <HoverBorderGradient
                            as="button"
                            containerClassName="rounded-lg border border-gray-500"
                            className="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white font-semibold transition-colors"
                        >
                            Cancel
                        </HoverBorderGradient>
                    </AlertDialogCancel>

                    <AlertDialogAction asChild>
                        <HoverBorderGradient
                            as="button"
                            containerClassName="rounded-lg"
                            className={`px-4 py-2 font-semibold text-white transition-colors ${
                                !allScoresFilled
                                    ? "bg-blue-400 cursor-not-allowed"
                                    : "bg-blue-600 hover:bg-blue-700"
                            }`}
                            disabled={!allScoresFilled}
                            onClick={handleSubmit}
                        >
                            Submit
                        </HoverBorderGradient>
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
};

export default ScoreAlertDialog;
