import React, { useState } from "react";
import { Eye, EyeOff, KeyRound, Printer, Trash2, UserPlus } from "lucide-react";
import PasswordConfirmDialog from "@/Components/PasswordConfirmDialog";
import usePasswordAction from "../usePasswordAction";
import send from "./request";

const input =
    "min-h-11 rounded-lg border-neutral-600 bg-neutral-800 text-white focus:border-yellow-400 focus:ring-yellow-400";
const icon =
    "grid h-11 w-11 place-items-center rounded-lg border border-neutral-600 bg-neutral-800 hover:bg-neutral-700 disabled:cursor-not-allowed disabled:opacity-30";

function JudgeRow({ judge, askDelete }) {
    const [name, setName] = useState(judge.name);
    const [shown, setShown] = useState(false);

    return (
        <li className="flex flex-wrap items-center gap-2 rounded-lg border border-neutral-700 p-3">
            <input
                aria-label="Judge name"
                className={`${input} min-w-40 flex-1`}
                value={name}
                onChange={(e) => setName(e.target.value)}
                onBlur={() => name.trim() && name !== judge.name && send("put", route("admin.event-judges.update", judge.id), { name }, "Judge renamed.")}
            />
            <code className="rounded bg-neutral-800 px-2 py-1 text-sm text-yellow-200">{judge.username}</code>
            {judge.password ? (
                <span className="flex items-center gap-1">
                    <code className="rounded bg-neutral-800 px-2 py-1 text-sm">{shown ? judge.password : "••••••••"}</code>
                    <button type="button" className={icon} aria-label={shown ? "Hide password" : "Show password"} onClick={() => setShown((s) => !s)}>
                        {shown ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                </span>
            ) : (
                <span className="text-sm text-gray-400">Changed by the judge</span>
            )}
            <span className="text-sm text-gray-400">{judge.score_count} scores</span>
            <button
                type="button"
                className={icon}
                aria-label={`Reset password for ${judge.name}`}
                title="Make a new password"
                onClick={() =>
                    window.confirm(`Make a new password for ${judge.name}? The old one stops working.`) &&
                    send("post", route("admin.event-judges.reset", judge.id), {}, "New password made.")
                }
            >
                <KeyRound className="h-4 w-4" />
            </button>
            <button
                type="button"
                className={`${icon} border-red-500/50 text-red-300`}
                aria-label={`Delete ${judge.name}`}
                disabled={judge.score_count > 0}
                title={judge.score_count > 0 ? "This judge has scores, so they can't be deleted." : undefined}
                onClick={() => askDelete(judge)}
            >
                <Trash2 className="h-4 w-4" />
            </button>
        </li>
    );
}

// The event's judge accounts. Averages divide by the number of judges listed here.
export default function Judges({ event, judges }) {
    const [count, setCount] = useState(1);
    const { ask, dialogProps } = usePasswordAction();

    const add = (e) => {
        e.preventDefault();
        send("post", route("admin.event-judges.store", event.id), { count }, `${count} judge account${count > 1 ? "s" : ""} created.`);
    };

    const askDelete = (judge) =>
        ask({
            url: route("admin.event-judges.destroy", judge.id),
            method: "delete",
            title: `Delete ${judge.name}?`,
            description: `${judge.username} won't be able to log in.`,
            confirmLabel: "Delete judge",
            danger: true,
            success: "Judge deleted.",
        });

    return (
        <div className="space-y-5 text-white">
            <p className="text-sm text-gray-300">
                Each judge gets a username and password for this event only. Averages divide by the number of judges here ({judges.length}).
            </p>

            <form onSubmit={add} className="flex flex-wrap items-end gap-2">
                <label className="text-sm text-gray-300">
                    How many judges to add?
                    <input type="number" min={1} max={30} className={`${input} mt-1 block w-28`} value={count} onChange={(e) => setCount(Number(e.target.value))} />
                </label>
                <button type="submit" className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-yellow-400 px-4 font-semibold text-black hover:bg-yellow-300">
                    <UserPlus className="h-4 w-4" aria-hidden="true" /> Add judges
                </button>
                {judges.length > 0 && (
                    <a
                        href={route("admin.event-judges.slips", event.id)}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-neutral-600 bg-neutral-800 px-4 hover:bg-neutral-700"
                    >
                        <Printer className="h-4 w-4" aria-hidden="true" /> Print credential slips
                    </a>
                )}
            </form>

            <ul className="space-y-2">
                {judges.map((j) => (
                    <JudgeRow key={`${j.id}-${j.name}-${j.password}`} judge={j} askDelete={askDelete} />
                ))}
            </ul>

            {dialogProps && <PasswordConfirmDialog {...dialogProps} />}
        </div>
    );
}
