import React, { useState } from "react";
import { ArrowDown, ArrowUp, Plus, Trash2 } from "lucide-react";
import send from "./request";

const input =
    "min-h-11 rounded-lg border-neutral-600 bg-neutral-800 text-white focus:border-yellow-400 focus:ring-yellow-400";
const icon =
    "grid h-11 w-11 place-items-center rounded-lg border border-neutral-600 bg-neutral-800 hover:bg-neutral-700 disabled:opacity-30 disabled:cursor-not-allowed";

function GroupRow({ group, index, count, swap }) {
    const [name, setName] = useState(group.name);
    const save = () => name.trim() && name !== group.name &&
        send("put", route("admin.groups.update", group.id), { name, position: group.position }, "Group renamed.");

    return (
        <li className="flex flex-wrap items-center gap-2 rounded-lg border border-neutral-700 p-3">
            <input
                aria-label="Group name"
                className={`${input} flex-1`}
                value={name}
                onChange={(e) => setName(e.target.value)}
                onBlur={save}
                onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), save())}
            />
            <span className="text-sm text-gray-400">{group.candidates} candidates</span>
            <button type="button" className={icon} aria-label="Move up" disabled={index === 0} onClick={() => swap(index, index - 1)}>
                <ArrowUp className="h-4 w-4" />
            </button>
            <button type="button" className={icon} aria-label="Move down" disabled={index === count - 1} onClick={() => swap(index, index + 1)}>
                <ArrowDown className="h-4 w-4" />
            </button>
            <button
                type="button"
                className={`${icon} border-red-500/50 text-red-300`}
                aria-label={`Delete ${group.name}`}
                disabled={group.candidates > 0}
                title={group.candidates > 0 ? "Move or delete its candidates first." : undefined}
                onClick={() => send("delete", route("admin.groups.destroy", group.id), {}, "Group deleted.")}
            >
                <Trash2 className="h-4 w-4" />
            </button>
        </li>
    );
}

// Groups are ranked separately and each gets its own Top N (e.g. Female, Male).
export default function Groups({ event, groups }) {
    const [name, setName] = useState("");

    // Reorder by swapping the two groups' positions.
    const swap = (a, b) => {
        const [first, second] = [groups[a], groups[b]];
        send("put", route("admin.groups.update", first.id), { name: first.name, position: second.position }, null, () =>
            send("put", route("admin.groups.update", second.id), { name: second.name, position: first.position })
        );
    };

    const add = (e) => {
        e.preventDefault();
        if (!name.trim()) return;
        send("post", route("admin.groups.store", event.id), { name }, "Group added.");
        setName("");
    };

    return (
        <div className="space-y-4 text-white">
            <p className="text-sm text-gray-300">Each group is ranked on its own and gets its own Top {event.finalists_per_group ?? "N"}.</p>
            <ul className="space-y-2">
                {groups.map((g, i) => (
                    <GroupRow key={`${g.id}-${g.position}-${g.name}`} group={g} index={i} count={groups.length} swap={swap} />
                ))}
            </ul>
            <form onSubmit={add} className="flex gap-2">
                <input aria-label="New group name" placeholder="e.g. Female" className={`${input} flex-1`} value={name} onChange={(e) => setName(e.target.value)} />
                <button type="submit" className="inline-flex min-h-11 items-center gap-1 rounded-lg bg-yellow-400 px-4 font-semibold text-black hover:bg-yellow-300">
                    <Plus className="h-4 w-4" /> Add group
                </button>
            </form>
        </div>
    );
}
