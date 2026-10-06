import React, { useState } from "react";
import { ArrowDown, ArrowUp, Plus, Trash2, Wand2 } from "lucide-react";
import Modal from "@/Components/Modal";
import { CATEGORY_ICONS, autoIconKey } from "@/lib/categoryIcon";
import { cn } from "@/lib/utils";
import send from "./request";
import useConfirmDelete from "./useConfirmDelete";
import { checkMaxPoints, checkName } from "./validate";

const input =
    "min-h-11 rounded-lg border-neutral-600 bg-neutral-800 text-white focus:border-yellow-400 focus:ring-yellow-400 disabled:opacity-50";
const icon =
    "grid h-11 w-11 place-items-center rounded-lg border border-neutral-600 bg-neutral-800 hover:bg-neutral-700 disabled:opacity-30 disabled:cursor-not-allowed";

// The sidebar icon of a category: a button showing the current icon that opens a grid
// of choices. "Auto" (value null) picks one from the name, as the sidebar does.
function IconPicker({ value, name, autoIndex, onChange }) {
    const [open, setOpen] = useState(false);
    const autoKey = autoIconKey(name, autoIndex);
    const current = CATEGORY_ICONS[value] ?? CATEGORY_ICONS[autoKey];
    const Current = current.icon;

    const choose = (key) => {
        setOpen(false);
        if (key !== value) onChange(key);
    };

    const option = (key, Icon, label, selected) => (
        <button
            key={key ?? "auto"}
            type="button"
            onClick={() => choose(key)}
            aria-pressed={selected}
            title={label}
            className={cn(
                "flex min-h-16 flex-col items-center justify-center gap-1 rounded-lg border p-2 text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400",
                selected
                    ? "border-yellow-400 bg-yellow-400/10 text-yellow-300"
                    : "border-neutral-700 bg-neutral-800 text-gray-200 hover:bg-neutral-700",
            )}
        >
            <Icon className="h-6 w-6" aria-hidden="true" />
            <span className="w-full truncate text-center">{label}</span>
        </button>
    );

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                aria-label={`Icon: ${value ? current.label : `Auto (${current.label})`}. Change icon`}
                title="Change icon"
                className={cn(icon, "relative text-yellow-300")}
            >
                <Current className="h-5 w-5" aria-hidden="true" />
                {!value && (
                    <Wand2 className="absolute -right-1 -top-1 h-3.5 w-3.5 rounded-full bg-neutral-900 p-0.5 text-gray-300" aria-hidden="true" />
                )}
            </button>
            {open && (
                <Modal show onClose={() => setOpen(false)} maxWidth="lg">
                    <div className="bg-neutral-900 p-6 text-white">
                        <h2 className="text-lg font-semibold">Icon for {name || "new category"}</h2>
                        <p className="mt-1 text-sm text-gray-400">Shown next to the category in the sidebar.</p>
                        <div className="mt-4 grid grid-cols-4 gap-2 sm:grid-cols-6">
                            {option(null, CATEGORY_ICONS[autoKey].icon, "Auto", !value)}
                            {Object.entries(CATEGORY_ICONS).map(([key, { icon: Icon, label }]) =>
                                option(key, Icon, label, value === key),
                            )}
                        </div>
                        <div className="mt-4 flex justify-end">
                            <button type="button" onClick={() => setOpen(false)} className="min-h-11 rounded-lg border border-neutral-600 bg-neutral-800 px-4 font-semibold hover:bg-neutral-700">
                                Cancel
                            </button>
                        </div>
                    </div>
                </Modal>
            )}
        </>
    );
}

function FieldErrors({ id, messages }) {
    const list = messages.filter(Boolean);
    if (list.length === 0) return null;
    return (
        <div id={id} role="alert" className="basis-full space-y-0.5 text-sm text-red-400">
            {list.map((m) => <p key={m}>{m}</p>)}
        </div>
    );
}

// Enter saves (by leaving the field), Escape puts the saved value back.
const editKeys = (revert) => (e) => {
    if (e.key === "Enter") {
        e.preventDefault();
        e.currentTarget.blur();
    } else if (e.key === "Escape") {
        revert();
    }
};

function CategoryRow({ category, index, autoIndex, count, swap, roundLabel, askDelete }) {
    const [name, setName] = useState(category.name);
    const [max, setMax] = useState(category.max_score);
    const [nameError, setNameError] = useState(null);
    const [maxError, setMaxError] = useState(null);
    const [saveError, setSaveError] = useState(null);
    const [saving, setSaving] = useState(false);
    const errorId = `category-${category.id}-errors`;

    // A failed save puts the saved values back and says why.
    const save = (next) => {
        setSaving(true);
        setSaveError(null);
        send("put", route("admin.categories.update", category.id), {
            name: category.name,
            icon: category.icon,
            round: category.round,
            max_score: category.max_score,
            position: category.position,
            ...next,
        }, "Category saved.", null, {
            onError: (message) => {
                setSaveError(message);
                setName(category.name);
                setMax(category.max_score);
            },
            onFinish: () => setSaving(false),
        });
    };

    const revertName = () => {
        setName(category.name);
        setNameError(null);
    };
    const commitName = () => {
        const trimmed = name.trim();
        if (trimmed === category.name) return revertName();
        const problem = checkName(name, { what: "category name" });
        setNameError(problem);
        if (!problem) save({ name: trimmed });
    };

    const revertMax = () => {
        setMax(category.max_score);
        setMaxError(null);
    };
    const commitMax = () => {
        const problem = checkMaxPoints(max);
        setMaxError(problem);
        if (!problem && Number(max) !== category.max_score) save({ max_score: Number(max) });
    };

    const invalid = (error) => ({ "aria-invalid": Boolean(error), "aria-describedby": error ? errorId : undefined });

    return (
        <li className="flex flex-wrap items-center gap-2 rounded-lg border border-neutral-700 p-3" aria-busy={saving}>
            <IconPicker value={category.icon} name={name} autoIndex={autoIndex} onChange={(key) => save({ icon: key })} />
            <input
                aria-label="Category name"
                className={cn(input, "min-w-40 flex-1", nameError && "border-red-500")}
                value={name}
                maxLength={80}
                onChange={(e) => setName(e.target.value)}
                onBlur={commitName}
                onKeyDown={editKeys(revertName)}
                {...invalid(nameError)}
            />
            <label className="flex items-center gap-2 text-sm text-gray-300">
                Max
                <input
                    type="number"
                    min="0.01"
                    max="999.99"
                    step="0.01"
                    inputMode="decimal"
                    aria-label="Max points"
                    className={cn(input, "w-24", maxError && "border-red-500")}
                    value={max}
                    disabled={category.hasScores}
                    title={category.hasScores ? "Locked: judges have scored this category." : undefined}
                    onChange={(e) => setMax(e.target.value)}
                    onBlur={commitMax}
                    onKeyDown={editKeys(revertMax)}
                    {...invalid(maxError)}
                />
            </label>
            <button type="button" className={icon} aria-label="Move up" disabled={index === 0 || saving} onClick={() => swap(index, index - 1)}>
                <ArrowUp className="h-4 w-4" />
            </button>
            <button type="button" className={icon} aria-label="Move down" disabled={index === count - 1 || saving} onClick={() => swap(index, index + 1)}>
                <ArrowDown className="h-4 w-4" />
            </button>
            <button
                type="button"
                className={`${icon} border-red-500/50 text-red-300`}
                aria-label={`Delete ${category.name}`}
                disabled={category.hasScores || saving}
                title={category.hasScores ? "This category has scores, so it can't be deleted." : undefined}
                onClick={() =>
                    askDelete({
                        title: `Delete “${category.name}”?`,
                        description: `It's removed from ${roundLabel} and from the judges' scoring pages. This can't be undone.`,
                        confirmLabel: "Delete category",
                        url: route("admin.categories.destroy", category.id),
                        success: "Category deleted.",
                    })
                }
            >
                <Trash2 className="h-4 w-4" />
            </button>
            <FieldErrors id={errorId} messages={[nameError, maxError, saveError]} />
        </li>
    );
}

function RoundSection({ event, round, label, categories, firstIndex, locked, askDelete }) {
    const [name, setName] = useState("");
    const [max, setMax] = useState("");
    const [newIcon, setNewIcon] = useState(null);
    const [errors, setErrors] = useState({});
    const [adding, setAdding] = useState(false);
    const total = categories.reduce((sum, c) => sum + Number(c.max_score), 0);
    const errorId = `new-category-${round}-errors`;

    const swap = (a, b) => {
        const [first, second] = [categories[a], categories[b]];
        const fields = (c, position) => ({ name: c.name, icon: c.icon, round: c.round, max_score: c.max_score, position });
        send("put", route("admin.categories.update", first.id), fields(first, second.position), null, () =>
            send("put", route("admin.categories.update", second.id), fields(second, first.position))
        );
    };

    const add = (e) => {
        e.preventDefault();
        if (adding) return;
        const problems = { name: checkName(name, { what: "category name" }), max_score: checkMaxPoints(max) };
        if (problems.name || problems.max_score) return setErrors(problems);

        setErrors({});
        setAdding(true);
        // Keep what was typed until the server accepts it.
        send("post", route("admin.categories.store", event.id), { name: name.trim(), icon: newIcon, round, max_score: Number(max) }, "Category added.", () => {
            setName("");
            setMax("");
            setNewIcon(null);
        }, {
            onError: (message, serverErrors) => setErrors(Object.keys(serverErrors).length ? serverErrors : { form: message }),
            onFinish: () => setAdding(false),
        });
    };

    const invalid = (key) => ({ "aria-invalid": Boolean(errors[key]), "aria-describedby": errors[key] ? errorId : undefined });

    return (
        <section className="space-y-3">
            <div className="flex items-baseline justify-between">
                <h3 className="font-semibold text-yellow-300">{label}</h3>
                <span className="text-sm text-gray-300">Max total: {total} pts</span>
            </div>
            <ul className="space-y-2">
                {categories.map((c, i) => (
                    <CategoryRow
                        key={`${c.id}-${c.position}-${c.name}-${c.max_score}-${c.icon}`}
                        category={c}
                        index={i}
                        autoIndex={firstIndex + i}
                        count={categories.length}
                        swap={swap}
                        roundLabel={label}
                        askDelete={askDelete}
                    />
                ))}
            </ul>
            {locked ? (
                <p className="text-sm text-gray-400">This round already has scores, so no new categories can be added.</p>
            ) : (
                <form onSubmit={add} noValidate className="flex flex-wrap gap-2">
                    <IconPicker value={newIcon} name={name} autoIndex={firstIndex + categories.length} onChange={setNewIcon} />
                    <input
                        aria-label="New category name"
                        placeholder="Category name"
                        maxLength={80}
                        className={cn(input, "min-w-40 flex-1", errors.name && "border-red-500")}
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        {...invalid("name")}
                    />
                    <input
                        type="number"
                        min="0.01"
                        max="999.99"
                        step="0.01"
                        inputMode="decimal"
                        aria-label="Max points"
                        placeholder="Max"
                        className={cn(input, "w-24", errors.max_score && "border-red-500")}
                        value={max}
                        onChange={(e) => setMax(e.target.value)}
                        {...invalid("max_score")}
                    />
                    <button type="submit" disabled={adding} className="inline-flex min-h-11 items-center gap-1 rounded-lg bg-yellow-400 px-4 font-semibold text-black hover:bg-yellow-300 disabled:opacity-50">
                        <Plus className="h-4 w-4" /> {adding ? "Adding…" : "Add"}
                    </button>
                    <FieldErrors id={errorId} messages={Object.values(errors)} />
                </form>
            )}
        </section>
    );
}

// Categories per round, each with the most points one judge can give.
export default function Categories({ event, categories, locks }) {
    const n = event.finalists_per_group;
    const rounds = event.rounds === 1 ? [[1, "Categories"]] : [[1, `Round 1 — Top ${n} Selection`], [2, `Round 2 — Top ${n} Finalist`]];
    const [askDelete, deleteDialog] = useConfirmDelete();

    return (
        <div className="space-y-8 text-white">
            {rounds.map(([round, label]) => (
                <RoundSection
                    key={round}
                    event={event}
                    round={round}
                    label={label}
                    categories={categories.filter((c) => c.round === round)}
                    // Auto icons are numbered across the whole menu, round 1 first (like the sidebar).
                    firstIndex={categories.filter((c) => c.round < round).length}
                    locked={Boolean(locks.roundHasScores?.[round])}
                    askDelete={askDelete}
                />
            ))}
            {deleteDialog}
        </div>
    );
}
