import React, { useState } from "react";
import { ArrowDown, ArrowUp, Plus, Trash2 } from "lucide-react";
import send from "./request";

const input =
    "min-h-11 rounded-lg border-neutral-600 bg-neutral-800 text-white focus:border-yellow-400 focus:ring-yellow-400 disabled:opacity-50";
const icon =
    "grid h-11 w-11 place-items-center rounded-lg border border-neutral-600 bg-neutral-800 hover:bg-neutral-700 disabled:opacity-30 disabled:cursor-not-allowed";

function CategoryRow({ category, index, count, swap }) {
    const [name, setName] = useState(category.name);
    const [max, setMax] = useState(category.max_score);

    const save = (next) =>
        send("put", route("admin.categories.update", category.id), {
            name: category.name,
            round: category.round,
            max_score: category.max_score,
            position: category.position,
            ...next,
        }, "Category saved.");

    return (
        <li className="flex flex-wrap items-center gap-2 rounded-lg border border-neutral-700 p-3">
            <input
                aria-label="Category name"
                className={`${input} min-w-40 flex-1`}
                value={name}
                onChange={(e) => setName(e.target.value)}
                onBlur={() => name.trim() && name !== category.name && save({ name })}
            />
            <label className="flex items-center gap-2 text-sm text-gray-300">
                Max
                <input
                    type="number"
                    min="0.01"
                    max="999.99"
                    step="0.01"
                    aria-label="Max points"
                    className={`${input} w-24`}
                    value={max}
                    disabled={category.hasScores}
                    title={category.hasScores ? "Locked: judges have scored this category." : undefined}
                    onChange={(e) => setMax(e.target.value)}
                    onBlur={() => Number(max) !== category.max_score && save({ max_score: max })}
                />
            </label>
            <button type="button" className={icon} aria-label="Move up" disabled={index === 0} onClick={() => swap(index, index - 1)}>
                <ArrowUp className="h-4 w-4" />
            </button>
            <button type="button" className={icon} aria-label="Move down" disabled={index === count - 1} onClick={() => swap(index, index + 1)}>
                <ArrowDown className="h-4 w-4" />
            </button>
            <button
                type="button"
                className={`${icon} border-red-500/50 text-red-300`}
                aria-label={`Delete ${category.name}`}
                disabled={category.hasScores}
                title={category.hasScores ? "This category has scores, so it can't be deleted." : undefined}
                onClick={() => send("delete", route("admin.categories.destroy", category.id), {}, "Category deleted.")}
            >
                <Trash2 className="h-4 w-4" />
            </button>
        </li>
    );
}

function RoundSection({ event, round, label, categories, locked }) {
    const [name, setName] = useState("");
    const [max, setMax] = useState("");
    const total = categories.reduce((sum, c) => sum + Number(c.max_score), 0);

    const swap = (a, b) => {
        const [first, second] = [categories[a], categories[b]];
        const fields = (c, position) => ({ name: c.name, round: c.round, max_score: c.max_score, position });
        send("put", route("admin.categories.update", first.id), fields(first, second.position), null, () =>
            send("put", route("admin.categories.update", second.id), fields(second, first.position))
        );
    };

    const add = (e) => {
        e.preventDefault();
        if (!name.trim() || !max) return;
        send("post", route("admin.categories.store", event.id), { name, round, max_score: max }, "Category added.");
        setName("");
        setMax("");
    };

    return (
        <section className="space-y-3">
            <div className="flex items-baseline justify-between">
                <h3 className="font-semibold text-yellow-300">{label}</h3>
                <span className="text-sm text-gray-300">Max total: {total} pts</span>
            </div>
            <ul className="space-y-2">
                {categories.map((c, i) => (
                    <CategoryRow key={`${c.id}-${c.position}-${c.name}-${c.max_score}`} category={c} index={i} count={categories.length} swap={swap} />
                ))}
            </ul>
            {locked ? (
                <p className="text-sm text-gray-400">This round already has scores, so no new categories can be added.</p>
            ) : (
                <form onSubmit={add} className="flex flex-wrap gap-2">
                    <input aria-label="New category name" placeholder="Category name" className={`${input} min-w-40 flex-1`} value={name} onChange={(e) => setName(e.target.value)} />
                    <input type="number" min="0.01" max="999.99" step="0.01" aria-label="Max points" placeholder="Max" className={`${input} w-24`} value={max} onChange={(e) => setMax(e.target.value)} />
                    <button type="submit" className="inline-flex min-h-11 items-center gap-1 rounded-lg bg-yellow-400 px-4 font-semibold text-black hover:bg-yellow-300">
                        <Plus className="h-4 w-4" /> Add
                    </button>
                </form>
            )}
        </section>
    );
}

// Categories per round, each with the most points one judge can give.
export default function Categories({ event, categories, locks }) {
    const n = event.finalists_per_group;
    const rounds = event.rounds === 1 ? [[1, "Categories"]] : [[1, `Round 1 — Top ${n} Selection`], [2, `Round 2 — Top ${n} Finalist`]];

    return (
        <div className="space-y-8 text-white">
            {rounds.map(([round, label]) => (
                <RoundSection
                    key={round}
                    event={event}
                    round={round}
                    label={label}
                    categories={categories.filter((c) => c.round === round)}
                    locked={Boolean(locks.roundHasScores?.[round])}
                />
            ))}
        </div>
    );
}
