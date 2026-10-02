"use client";

import React from "react";
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableCell,
    TableHead,
    TableCaption,
} from "@/components/ui/table";
import PrintButton from "./PrintButton";
import CandidatePhoto from "@/Components/CandidatePhoto";

// Display names that differ from the category key.
const CATEGORY_LABELS = {
    casual_wear: "Sports Wear",
    closed_door_interview: "Casual Interview",
};

// Highest score a judge can give per category; each round adds up to 100.
const CATEGORY_MAX = {
    production_number: 10,
    casual_wear: 25,
    swim_wear: 25,
    formal_wear: 25,
    closed_door_interview: 15,
    face_and_figure: 50,
    delivery: 40,
    overall_appeal: 10,
};

const TopFiveSelectionTable = ({
    title,
    candidates,
    categories,
    category,
    judges = [],
}) => {
    const tableRef = React.useRef();

    return (
        <div className="p-4 mb-8">
            <div className="flex justify-between items-center mb-4">
                <h2 className="text-white text-xl font-bold mb-4">{title}</h2>
                <PrintButton
                    title={title}
                    tableRef={tableRef}
                    category={category}
                    judges={judges}
                />
            </div>

            <div ref={tableRef}>
                <h2 className="text-center text-2xl font-bold text-black bg-white py-4 print:block hidden">
                    {category} Results
                </h2>

                <Table className="bg-neutral-900 text-white border border-gray-700">
                    <TableCaption className="text-white">{title}</TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead>#</TableHead>
                            <TableHead>Candidate</TableHead>
                            {categories.map((cat) => (
                                <TableHead key={cat} className="text-center">
                                    {(
                                        CATEGORY_LABELS[cat] ??
                                        cat.replaceAll("_", " ")
                                    ).toUpperCase()}
                                    {CATEGORY_MAX[cat] && (
                                        <span className="block text-xs font-normal opacity-70">
                                            out of {CATEGORY_MAX[cat]}
                                        </span>
                                    )}
                                </TableHead>
                            ))}
                            <TableHead className="text-center">
                                Total
                                <span className="block text-xs font-normal opacity-70">
                                    out of 100
                                </span>
                            </TableHead>
                            <TableHead className="text-center"> Rank</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {candidates.map((c) => (
                            <TableRow
                                key={c.candidate.id}
                                className={
                                    c.rank <= 3
                                        ? "bg-yellow-600 text-black font-bold hover:bg-yellow-500"
                                        : ""
                                }
                            >
                                <TableCell>
                                    {c.candidate.candidate_number}
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center gap-2">
                                        <CandidatePhoto
                                            path={c.candidate.profile_img}
                                            size="thumb"
                                            alt={`${c.candidate.first_name} ${c.candidate.last_name}`}
                                            width={32}
                                            height={32}
                                            className="w-8 h-8 rounded-full object-cover"
                                        />
                                        <span>
                                            {c.candidate.first_name}{" "}
                                            {c.candidate.last_name}
                                        </span>
                                    </div>
                                </TableCell>
                                {categories.map((cat) => (
                                    <TableCell
                                        key={cat}
                                        className="text-center"
                                    >
                                        {Number(c.scores[cat] ?? 0).toFixed(2)}
                                    </TableCell>
                                ))}
                                <TableCell className="text-center">
                                    {Number(c.total).toFixed(2)}
                                </TableCell>
                                <TableCell className="text-center">
                                    {c.rank}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
};

export default TopFiveSelectionTable;
