"use client";

import React, { useMemo } from "react";
import { useForm } from "@inertiajs/react";
import { toast } from "sonner";
import { BellRing, CheckCircle2, Clock, Loader2, Send, Users } from "lucide-react";
import PageLayout from "@/Layouts/PageLayout";

const MESSAGE_MAX = 200;

const focusRing =
    "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-300 focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-900";

const fieldClass =
    "block w-full rounded-lg border border-neutral-600 bg-neutral-800 text-base text-white placeholder-gray-500 transition-colors duration-200 focus:border-yellow-400 focus:outline-none focus:ring-2 focus:ring-yellow-400/40";

function timeAgo(iso) {
    const seconds = Math.max(0, Math.round((Date.now() - new Date(iso)) / 1000));
    if (seconds < 60) return "just now";
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return `${minutes} min ago`;
    const hours = Math.round(minutes / 60);
    return `${hours} hr${hours > 1 ? "s" : ""} ago`;
}

// "Done", "4 of 11" or "Not started" for one judge in the chosen category.
function ProgressBadge({ scored, total }) {
    if (!total) return <span className="text-xs text-gray-400">No candidates yet</span>;

    if (scored >= total) {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-green-500/15 px-2 py-0.5 text-xs font-medium text-green-300">
                <CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" />
                Done
            </span>
        );
    }

    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                scored > 0 ? "bg-yellow-400/15 text-yellow-300" : "bg-white/10 text-gray-300"
            }`}
        >
            <Clock className="h-3.5 w-3.5" aria-hidden="true" />
            {scored > 0 ? `${scored} of ${total}` : "Not started"}
        </span>
    );
}

const NotifyJudges = ({ judges = [], categories = [], progress = {}, recent = [], sendUrl, event }) => {
    // Round headings in category order (e.g. "Top 3 Selection", "Top 3 Finalist").
    const rounds = [...new Set(categories.map((c) => c.round))];
    // Suggested message for a category ("" = general reminder).
    const defaultFor = (key) => {
        const match = categories.find((c) => String(c.key) === String(key));
        return match
            ? `Please score the candidates for ${match.label}.`
            : "Please check your scoring sheets.";
    };

    const { data, setData, post, processing, errors, transform } = useForm({
        category: "",
        message: defaultFor(""),
        audience: "all",
        judge_ids: [],
    });

    // Switching category refreshes the suggested message, unless the admin
    // already wrote their own (then it's left alone).
    const changeCategory = (key) =>
        setData((d) => ({
            ...d,
            category: key,
            message:
                d.message.trim() === "" || d.message === defaultFor(d.category)
                    ? defaultFor(key)
                    : d.message,
        }));

    const category = categories.find((c) => String(c.key) === String(data.category));
    const categoryProgress = progress[data.category];
    const scoredBy = (judgeId) => categoryProgress?.scored?.[judgeId] ?? 0;

    const unfinished = useMemo(
        () =>
            categoryProgress
                ? judges.filter((j) => scoredBy(j.id) < categoryProgress.total).map((j) => j.id)
                : [],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [data.category, judges, progress]
    );

    const defaultMessage = defaultFor(data.category);
    const isEdited = data.message !== defaultMessage;

    const recipientCount = data.audience === "all" ? judges.length : data.judge_ids.length;
    const canSend = recipientCount > 0 && !processing;

    const toggleJudge = (id) =>
        setData(
            "judge_ids",
            data.judge_ids.includes(id)
                ? data.judge_ids.filter((x) => x !== id)
                : [...data.judge_ids, id]
        );

    const submit = (e) => {
        e.preventDefault();

        transform((d) => ({
            category: d.category || null,
            message: d.message.trim() || null,
            judge_ids: d.audience === "all" ? null : d.judge_ids,
        }));

        post(sendUrl, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    `Notification sent to ${
                        data.audience === "all"
                            ? "all judges"
                            : `${recipientCount} judge${recipientCount > 1 ? "s" : ""}`
                    }.`
                );
                // Ready for the next one: back to the suggested message.
                setData("message", defaultFor(data.category));
            },
            onError: () => toast.error("Couldn't send the notification. Check the form."),
        });
    };

    const judgeName = (id) => judges.find((j) => j.id === id)?.name ?? "a judge";

    return (
        <>
            <div className="p-4 md:p-8">
                <div className="mb-6">
                    <h2 className="flex items-center gap-2 text-xl font-bold text-white">
                        <BellRing className="h-6 w-6 text-yellow-400" aria-hidden="true" />
                        Notify Judges
                    </h2>
                    <p className="mt-1 text-sm text-gray-400">
                        Send a reminder to the judges' screens. They see it within a few
                        seconds, with a button that opens the right scoring sheet.
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    {/* Compose */}
                    <form
                        onSubmit={submit}
                        className="space-y-6 rounded-2xl border border-white/10 bg-neutral-900 p-5 text-white sm:p-6"
                    >
                        <div>
                            <label htmlFor="category" className="block text-sm font-medium text-gray-300">
                                Category to score
                            </label>
                            <select
                                id="category"
                                value={data.category}
                                onChange={(e) => changeCategory(e.target.value)}
                                className={`mt-2 min-h-12 ${fieldClass}`}
                                aria-describedby={errors.category ? "category-error" : undefined}
                            >
                                <option value="">General reminder (no category)</option>
                                {rounds.map((round) => (
                                    <optgroup key={round} label={round}>
                                        {categories
                                            .filter((c) => c.round === round)
                                            .map((c) => (
                                                <option key={c.key} value={c.key}>
                                                    {c.label}
                                                </option>
                                            ))}
                                    </optgroup>
                                ))}
                            </select>
                            {errors.category && (
                                <p id="category-error" role="alert" className="mt-2 text-sm text-red-400">
                                    {errors.category}
                                </p>
                            )}
                        </div>

                        <div>
                            <div className="flex items-baseline justify-between">
                                <label htmlFor="message" className="block text-sm font-medium text-gray-300">
                                    Message
                                </label>
                                <span className="text-xs text-gray-400" aria-live="polite">
                                    {data.message.length}/{MESSAGE_MAX}
                                </span>
                            </div>
                            <textarea
                                id="message"
                                rows={3}
                                maxLength={MESSAGE_MAX}
                                value={data.message}
                                onChange={(e) => setData("message", e.target.value)}
                                placeholder={defaultMessage}
                                className={`mt-2 resize-y ${fieldClass}`}
                                aria-describedby="message-help"
                            />
                            <div className="mt-1 flex flex-wrap items-center justify-between gap-2">
                                <p id="message-help" className="text-xs text-gray-400">
                                    Filled in for you. Edit it as you like before sending.
                                </p>
                                {isEdited && (
                                    <button
                                        type="button"
                                        onClick={() => setData("message", defaultMessage)}
                                        className={`min-h-11 cursor-pointer rounded-md px-2 text-xs font-medium text-yellow-300 underline-offset-2 hover:underline ${focusRing}`}
                                    >
                                        Reset to suggested message
                                    </button>
                                )}
                            </div>
                        </div>

                        <fieldset>
                            <legend className="text-sm font-medium text-gray-300">Send to</legend>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {[
                                    ["all", `All judges (${judges.length})`],
                                    ["some", "Choose judges"],
                                ].map(([value, label]) => (
                                    <label
                                        key={value}
                                        className={`flex min-h-11 cursor-pointer items-center gap-2 rounded-full border px-4 text-sm transition-colors duration-200 ${
                                            data.audience === value
                                                ? "border-yellow-400 bg-yellow-400/10 text-yellow-200"
                                                : "border-neutral-600 text-gray-300 hover:border-neutral-400"
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="audience"
                                            value={value}
                                            checked={data.audience === value}
                                            onChange={() => setData("audience", value)}
                                            className="h-4 w-4 border-neutral-500 bg-neutral-800 text-yellow-400 focus:ring-yellow-400 focus:ring-offset-neutral-900"
                                        />
                                        {label}
                                    </label>
                                ))}
                            </div>

                            {data.audience === "some" && (
                                <div className="mt-4">
                                    {category && (
                                        <button
                                            type="button"
                                            onClick={() => setData("judge_ids", unfinished)}
                                            disabled={unfinished.length === 0}
                                            className={`mb-3 inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-neutral-600 px-3 text-sm text-gray-200 transition-colors duration-200 hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-50 ${focusRing}`}
                                        >
                                            <Users className="h-4 w-4" aria-hidden="true" />
                                            {unfinished.length
                                                ? `Select judges who haven't finished (${unfinished.length})`
                                                : `Everyone has finished ${category.label}`}
                                        </button>
                                    )}

                                    <ul className="divide-y divide-white/5 overflow-hidden rounded-lg border border-white/10">
                                        {judges.map((judge) => (
                                            <li key={judge.id}>
                                                <label className="flex min-h-12 cursor-pointer items-center gap-3 px-4 py-2 hover:bg-neutral-800">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.judge_ids.includes(judge.id)}
                                                        onChange={() => toggleJudge(judge.id)}
                                                        className="h-5 w-5 rounded border-neutral-600 bg-neutral-800 text-yellow-400 focus:ring-yellow-400 focus:ring-offset-neutral-900"
                                                    />
                                                    <span className="flex-1 text-sm">{judge.name}</span>
                                                    {categoryProgress && (
                                                        <ProgressBadge
                                                            scored={scoredBy(judge.id)}
                                                            total={categoryProgress.total}
                                                        />
                                                    )}
                                                </label>
                                            </li>
                                        ))}
                                    </ul>
                                    {errors["judge_ids.0"] && (
                                        <p role="alert" className="mt-2 text-sm text-red-400">
                                            {errors["judge_ids.0"]}
                                        </p>
                                    )}
                                </div>
                            )}
                        </fieldset>

                        <button
                            type="submit"
                            disabled={!canSend}
                            aria-busy={processing}
                            className={`inline-flex min-h-12 w-full cursor-pointer items-center justify-center gap-2 rounded-full bg-yellow-400 px-6 font-semibold text-black transition duration-200 hover:bg-yellow-300 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto ${focusRing}`}
                        >
                            {processing ? (
                                <Loader2 className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />
                            ) : (
                                <Send className="h-5 w-5" aria-hidden="true" />
                            )}
                            {data.audience === "all"
                                ? "Send to all judges"
                                : recipientCount
                                ? `Send to ${recipientCount} judge${recipientCount > 1 ? "s" : ""}`
                                : "Select at least one judge"}
                        </button>
                    </form>

                    {/* Recently sent */}
                    <section
                        aria-labelledby="recent-heading"
                        className="rounded-2xl border border-white/10 bg-neutral-900 p-5 text-white sm:p-6"
                    >
                        <h3 id="recent-heading" className="text-base font-semibold">
                            Recently sent
                        </h3>
                        {recent.length === 0 ? (
                            <p className="mt-4 text-sm text-gray-400">No notifications sent yet.</p>
                        ) : (
                            <ul className="mt-4 space-y-3">
                                {recent.map((n) => (
                                    <li key={n.id} className="rounded-lg border border-white/10 bg-black/30 p-3">
                                        <p className="text-sm text-white">{n.message}</p>
                                        <p className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-400">
                                            {n.label && (
                                                <span className="rounded-full bg-yellow-400/10 px-2 py-0.5 text-yellow-300">
                                                    {n.label}
                                                </span>
                                            )}
                                            <span>
                                                To{" "}
                                                {n.judge_ids
                                                    ? n.judge_ids.map(judgeName).join(", ")
                                                    : "all judges"}
                                            </span>
                                            <span aria-hidden="true">·</span>
                                            <time dateTime={n.sent_at}>{timeAgo(n.sent_at)}</time>
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
};

export default NotifyJudges;

// Persistent layout: the sidebar and live-update pollers stay mounted between pages
// (no remount, no extra poll request per click).
NotifyJudges.layout = (page) => <PageLayout>{page}</PageLayout>;
