import React from "react";
import candidateName from "@/lib/candidateName";
import PageLayout from "@/Layouts/PageLayout";
import TopFiveSelectionTable from "@/Pages/Admin/Partials/TopFiveSelectionTable";
import PrintButton from "@/Pages/Admin/Partials/PrintButton";
import CandidatePhoto from "@/Components/CandidatePhoto";
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableCell,
    TableHead,
} from "@/components/ui/table";

// Carry-over standings: Round 1 and Finals totals and the weighted final total.
function WeightedTable({ group, weights, judges, title }) {
    const tableRef = React.useRef();

    return (
        <div className="mb-8 p-4">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-xl font-bold text-white">{group.name} Candidates</h2>
                <PrintButton title={group.name} tableRef={tableRef} category={title} judges={judges} />
            </div>
            <div ref={tableRef}>
                <Table className="border border-gray-700 bg-neutral-900 text-white">
                    <TableHeader>
                        <TableRow>
                            <TableHead>#</TableHead>
                            <TableHead>Candidate</TableHead>
                            <TableHead className="text-center">Round 1 ({weights[0]}%)</TableHead>
                            <TableHead className="text-center">Finals ({weights[1]}%)</TableHead>
                            <TableHead className="text-center">Final Total</TableHead>
                            <TableHead className="text-center">Rank</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {group.rows.map((r) => (
                            <TableRow
                                key={r.candidate.id}
                                className={r.rank === 1 ? "bg-yellow-600 font-bold text-black hover:bg-yellow-500" : ""}
                            >
                                <TableCell>{r.candidate.candidate_number}</TableCell>
                                <TableCell>
                                    <div className="flex items-center gap-2">
                                        <CandidatePhoto
                                            path={r.candidate.profile_img}
                                            size="thumb"
                                            alt={candidateName(r.candidate)}
                                            width={32}
                                            height={32}
                                            className="h-8 w-8 rounded-full object-cover"
                                        />
                                        <span>
                                            {candidateName(r.candidate)}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell className="text-center">{Number(r.round1).toFixed(2)}</TableCell>
                                <TableCell className="text-center">{Number(r.finals).toFixed(2)}</TableCell>
                                <TableCell className="text-center">{Number(r.total).toFixed(2)}</TableCell>
                                <TableCell className="text-center">{r.rank}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}

export default function Standings({ event, mode, weights, categories = [], judges = [], groups }) {
    return (
        <>
            <h2 className="mt-6 mb-1 flex justify-center text-xl font-bold text-white">Final Standings</h2>
            <p className="mb-4 text-center text-sm text-gray-400">{event.name}</p>

            {groups.map((group) => {
                const title = `${event.name} — Final Standings ${group.name}`;

                return mode === "weighted" ? (
                    <WeightedTable key={group.id} group={group} weights={weights} judges={judges} title={title} />
                ) : (
                    <TopFiveSelectionTable
                        key={group.id}
                        title={`${group.name} Candidates`}
                        candidates={group.rows}
                        categories={categories}
                        judges={judges}
                        highlight={1}
                        category={title}
                    />
                );
            })}
        </>
    );
}

// Persistent layout: the sidebar and live-update pollers stay mounted between pages
// (no remount, no extra poll request per click).
Standings.layout = (page) => <PageLayout>{page}</PageLayout>;
