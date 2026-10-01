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

const ResultTable = ({ title, candidates, judgeOrder, category }) => {
    const tableRef = React.useRef();

    return (
        <div className="p-4 mb-8">
            <div className="flex justify-between items-center mb-4">
                <h2 className="text-white text-xl font-bold mb-4">{title}</h2>
                <PrintButton
                    title={title}
                    tableRef={tableRef}
                    category={category}
                />
            </div>

            {/* Include the heading inside the printable area */}
            <div ref={tableRef}>
                <h2 className="text-center text-2xl font-bold text-black bg-white py-4 print:block hidden">
                    {title} Results
                </h2>

                <Table className="bg-neutral-900 text-white border border-gray-700">
                    <TableCaption className="text-white">{title}</TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead>#</TableHead>
                            <TableHead>Candidate</TableHead>
                            {judgeOrder.map((judge) => (
                                <TableHead key={judge} className="text-center">
                                    {judge.replace("_", " ").toUpperCase()}
                                </TableHead>
                            ))}
                            <TableHead className="text-center">Total</TableHead>
                            <TableHead className="text-center">Rank</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {candidates.map((c) => (
                            <TableRow
                                key={c.candidate.id}
                                className={
                                    c.rank === 1
                                        ? "bg-yellow-600 text-black font-bold hover:bg-yellow-500"
                                        : ""
                                }
                            >
                                <TableCell>
                                    {c.candidate.candidate_number}
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center gap-2">
                                        <img
                                            src={
                                                c.candidate.profile_img
                                                    ? `/${c.candidate.profile_img.replace(
                                                          "admin/",
                                                          ""
                                                      )}`
                                                    : "/default-avatar.png"
                                            }
                                            alt={`${c.candidate.first_name} ${c.candidate.last_name}`}
                                            className="w-8 h-8 rounded-full object-cover"
                                        />
                                        <span>
                                            {c.candidate.first_name}{" "}
                                            {c.candidate.last_name}
                                        </span>
                                    </div>
                                </TableCell>
                                {judgeOrder.map((judge) => (
                                    <TableCell
                                        key={judge}
                                        className="text-center"
                                    >
                                        {Number(c.scores[judge] ?? 0).toFixed(
                                            2
                                        )}
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

export default ResultTable;
