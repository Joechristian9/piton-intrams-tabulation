import React from "react";
import { useForm } from "@inertiajs/react";
import { toast } from "sonner";

const field =
    "mt-1 block w-full rounded-lg border-neutral-600 bg-neutral-800 text-white placeholder-neutral-500 focus:border-yellow-400 focus:ring-yellow-400 disabled:opacity-50";

const Error = ({ message }) =>
    message ? (
        <p role="alert" className="mt-1 text-sm text-red-400">
            {message}
        </p>
    ) : null;

/**
 * Event settings form, for creating (no `event`) or editing. Fields that would
 * change submitted results are disabled once scoring has started (the server
 * enforces the same rules).
 */
export default function Settings({ event = null, locks = {}, onDone }) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: event?.name ?? "",
        code: event?.code ?? "",
        rounds: event?.rounds ?? 2,
        finalists_per_group: event?.finalists_per_group ?? 3,
        finals_from_zero: event?.finals_from_zero ?? true,
        round1_weight: event?.round1_weight ?? 40,
        finals_weight: event?.finals_weight ?? 60,
    });

    const scored = Boolean(locks.hasScores);
    const twoRounds = Number(data.rounds) === 2;

    const submit = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(event ? "Settings saved." : "Event created.");
                onDone?.();
            },
        };
        event ? put(route("admin.events.update", event.id), options) : post(route("admin.events.store"), options);
    };

    // Suggest a code from the name while creating.
    const setName = (name) => {
        setData((d) => ({
            ...d,
            name,
            code: event || d.code !== suggest(d.name) ? d.code : suggest(name),
        }));
    };

    return (
        <form onSubmit={submit} className="space-y-5 text-white">
            <div>
                <label htmlFor="ev-name" className="text-sm font-medium text-gray-300">Event name</label>
                <input id="ev-name" className={field} value={data.name} onChange={(e) => setName(e.target.value)} required />
                <Error message={errors.name} />
            </div>

            <div>
                <label htmlFor="ev-code" className="text-sm font-medium text-gray-300">Code</label>
                <input
                    id="ev-code"
                    className={field}
                    value={data.code}
                    onChange={(e) => setData("code", e.target.value)}
                    disabled={Boolean(locks.hasJudges)}
                    required
                />
                <p className="mt-1 text-xs text-gray-400">
                    Letters, numbers and dashes. Judge usernames start with it (e.g. {data.code || "code"}-judge1)
                    {locks.hasJudges ? ", so it can't change now." : "."}
                </p>
                <Error message={errors.code} />
            </div>

            <fieldset disabled={scored}>
                <legend className="text-sm font-medium text-gray-300">Rounds</legend>
                <div className="mt-2 flex gap-3">
                    {[
                        [1, "1 round"],
                        [2, "2 rounds (Top N + finals)"],
                    ].map(([value, label]) => (
                        <label key={value} className="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-neutral-700 px-3">
                            <input
                                type="radio"
                                name="rounds"
                                checked={Number(data.rounds) === value}
                                onChange={() => setData("rounds", value)}
                                className="text-yellow-400 focus:ring-yellow-400"
                            />
                            {label}
                        </label>
                    ))}
                </div>
                <Error message={errors.rounds} />
            </fieldset>

            {twoRounds && (
                <>
                    <div>
                        <label htmlFor="ev-n" className="text-sm font-medium text-gray-300">Finalists per group</label>
                        <input
                            id="ev-n"
                            type="number"
                            min={1}
                            max={50}
                            className={`${field} max-w-32`}
                            value={data.finalists_per_group ?? ""}
                            onChange={(e) => setData("finalists_per_group", e.target.value)}
                            disabled={Boolean(locks.finalistsSet)}
                        />
                        <Error message={errors.finalists_per_group} />
                    </div>

                    <fieldset disabled={scored} className="space-y-3">
                        <label className="flex min-h-11 cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                checked={Boolean(data.finals_from_zero)}
                                onChange={(e) => setData("finals_from_zero", e.target.checked)}
                                className="rounded text-yellow-400 focus:ring-yellow-400"
                            />
                            Finals start from zero
                        </label>
                        <Error message={errors.finals_from_zero} />
                        {!data.finals_from_zero && (
                            <div className="flex flex-wrap gap-4">
                                {[
                                    ["round1_weight", "Round 1 weight %"],
                                    ["finals_weight", "Finals weight %"],
                                ].map(([key, label]) => (
                                    <div key={key}>
                                        <label htmlFor={`ev-${key}`} className="text-sm font-medium text-gray-300">{label}</label>
                                        <input
                                            id={`ev-${key}`}
                                            type="number"
                                            min={0}
                                            max={100}
                                            className={`${field} max-w-32`}
                                            value={data[key] ?? ""}
                                            onChange={(e) => setData(key, e.target.value)}
                                        />
                                    </div>
                                ))}
                                <Error message={errors.round1_weight} />
                            </div>
                        )}
                    </fieldset>
                </>
            )}

            {scored && (
                <p className="text-sm text-yellow-300">
                    Scoring has started, so rounds and finals settings are locked. You can still rename the event.
                </p>
            )}

            <div className="flex justify-end">
                <button
                    type="submit"
                    disabled={processing}
                    className="min-h-11 rounded-lg bg-yellow-400 px-5 font-semibold text-black hover:bg-yellow-300 disabled:opacity-50"
                >
                    {event ? "Save settings" : "Create event"}
                </button>
            </div>
        </form>
    );
}

const suggest = (name) =>
    name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "")
        .slice(0, 20);
