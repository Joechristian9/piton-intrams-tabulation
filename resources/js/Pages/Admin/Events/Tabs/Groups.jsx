import React, { useState } from "react";
import { ArrowDown, ArrowUp, Plus, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import send from "./request";
import useConfirmDelete from "./useConfirmDelete";
import { checkName } from "./validate";

const input =
    "min-h-11 rounded-lg border-neutral-600 bg-neutral-800 text-white focus:border-yellow-400 focus:ring-yellow-400";
const icon =
    "grid h-11 w-11 place-items-center rounded-lg border border-neutral-600 bg-neutral-800 hover:bg-neutral-700 disabled:opacity-30 disabled:cursor-not-allowed";

const checkGroupName = (name) => checkName(name, { what: "group name", max: 60 });

function GroupRow({ group, index, count, swap, askDelete }) {
    const [name, setName] = useState(group.name);
    const [error, setError] = useState(null);
    const [saving, setSaving] = useState(false);
    const errorId = `group-${group.id}-error`;

    const revert = () => {
        setName(group.name);
        setError(null);
    };

    // A failed rename puts the saved name back and says why (e.g. a duplicate name).
    const save = () => {
        const trimmed = name.trim();
        if (trimmed === group.name) return revert();
        const problem = checkGroupName(name);
        setError(problem);
        if (problem) return;

        setSaving(true);
        send("put", route("admin.groups.update", group.id), { name: trimmed, position: group.position }, "Group renamed.", null, {
            onError: (message) => {
                setError(message);
                setName(group.name);
            },
            onFinish: () => setSaving(false),
        });
    };

    return (
        <li className="flex flex-wrap items-center gap-2 rounded-lg border border-neutral-700 p-3" aria-busy={saving}>
            <input
                aria-label="Group name"
                className={cn(input, "flex-1", error && "border-red-500")}
                value={name}
                maxLength={60}
                onChange={(e) => setName(e.target.value)}
                onBlur={save}
                onKeyDown={(e) => {
                    if (e.key === "Enter") {
                        e.preventDefault();
                        e.currentTarget.blur();
                    } else if (e.key === "Escape") {
                        revert();
                    }
                }}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? errorId : undefined}
            />
            <span className="text-sm text-gray-400">{group.candidates} candidates</span>
            <button type="button" className={icon} aria-label="Move up" disabled={index === 0 || saving} onClick={() => swap(index, index - 1)}>
                <ArrowUp className="h-4 w-4" />
            </button>
            <button type="button" className={icon} aria-label="Move down" disabled={index === count - 1 || saving} onClick={() => swap(index, index + 1)}>
                <ArrowDown className="h-4 w-4" />
            </button>
            <button
                type="button"
                className={`${icon} border-red-500/50 text-red-300`}
                aria-label={`Delete ${group.name}`}
                disabled={group.candidates > 0 || saving}
                title={group.candidates > 0 ? "Move or delete its candidates first." : undefined}
                onClick={() =>
                    askDelete({
                        title: `Delete the “${group.name}” group?`,
                        description: "It's removed from this event's setup and rankings. This can't be undone.",
                        confirmLabel: "Delete group",
                        url: route("admin.groups.destroy", group.id),
                        success: "Group deleted.",
                    })
                }
            >
                <Trash2 className="h-4 w-4" />
            </button>
            {error && (
                <p id={errorId} role="alert" className="basis-full text-sm text-red-400">
                    {error}
                </p>
            )}
        </li>
    );
}

// Groups are ranked separately and each gets its own Top N (e.g. Female, Male).
export default function Groups({ event, groups }) {
    const [name, setName] = useState("");
    const [error, setError] = useState(null);
    const [adding, setAdding] = useState(false);
    const [askDelete, deleteDialog] = useConfirmDelete();

    // Reorder by swapping the two groups' positions.
    const swap = (a, b) => {
        const [first, second] = [groups[a], groups[b]];
        send("put", route("admin.groups.update", first.id), { name: first.name, position: second.position }, null, () =>
            send("put", route("admin.groups.update", second.id), { name: second.name, position: first.position })
        );
    };

    const add = (e) => {
        e.preventDefault();
        if (adding) return;
        const problem = checkGroupName(name);
        setError(problem);
        if (problem) return;

        setAdding(true);
        // Keep what was typed until the server accepts it.
        send("post", route("admin.groups.store", event.id), { name: name.trim() }, "Group added.", () => setName(""), {
            onError: (message) => setError(message),
            onFinish: () => setAdding(false),
        });
    };

    return (
        <div className="space-y-4 text-white">
            <p className="text-sm text-gray-300">Each group is ranked on its own and gets its own Top {event.finalists_per_group ?? "N"}.</p>
            <ul className="space-y-2">
                {groups.map((g, i) => (
                    <GroupRow key={`${g.id}-${g.position}-${g.name}`} group={g} index={i} count={groups.length} swap={swap} askDelete={askDelete} />
                ))}
            </ul>
            <form onSubmit={add} noValidate className="flex flex-wrap gap-2">
                <input
                    aria-label="New group name"
                    placeholder="e.g. Female"
                    maxLength={60}
                    className={cn(input, "min-w-40 flex-1", error && "border-red-500")}
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? "new-group-error" : undefined}
                />
                <button type="submit" disabled={adding} className="inline-flex min-h-11 items-center gap-1 rounded-lg bg-yellow-400 px-4 font-semibold text-black hover:bg-yellow-300 disabled:opacity-50">
                    <Plus className="h-4 w-4" /> {adding ? "Adding…" : "Add group"}
                </button>
                {error && (
                    <p id="new-group-error" role="alert" className="basis-full text-sm text-red-400">
                        {error}
                    </p>
                )}
            </form>
            {deleteDialog}
        </div>
    );
}
