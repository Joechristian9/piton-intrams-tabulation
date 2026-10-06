import React, { useState } from "react";
import { usePage } from "@inertiajs/react";
import { Check, ListChecks, Palette, Pencil, Plus, Trash2, Trophy } from "lucide-react";
import PageLayout from "@/Layouts/PageLayout";
import Modal from "@/Components/Modal";
import send from "@/Pages/Admin/Events/Tabs/request";
import useConfirmDelete from "@/Pages/Admin/Events/Tabs/useConfirmDelete";
import { HEX, themeProblems, themeVariables } from "@/lib/themeColors";
import { cn } from "@/lib/utils";

const input =
    "min-h-11 rounded-lg border-neutral-600 bg-neutral-800 text-white focus:border-yellow-400 focus:ring-yellow-400";
const smallButton =
    "inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-neutral-600 bg-neutral-800 px-3 text-sm hover:bg-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-300 disabled:cursor-not-allowed disabled:opacity-40";

// A small mock of the app in one theme. It inherits the theme from the nearest
// data-theme (presets) or inline CSS variables (custom themes) around it.
function Preview() {
    return (
        <div className="flex h-28 overflow-hidden rounded-lg border border-neutral-700" aria-hidden="true">
            <div className="flex w-16 flex-col gap-1.5 bg-neutral-800 p-2">
                <span className="relative flex items-center gap-1 rounded bg-neutral-700/70 px-1 py-1">
                    <span className="absolute inset-y-1 left-0 w-0.5 rounded-full bg-amber-400" />
                    <ListChecks className="h-3.5 w-3.5 text-amber-400" />
                    <span className="h-1.5 flex-1 rounded bg-neutral-400" />
                </span>
                <span className="flex items-center gap-1 px-1 py-1">
                    <ListChecks className="h-3.5 w-3.5 text-neutral-200" />
                    <span className="h-1.5 flex-1 rounded bg-neutral-600" />
                </span>
                <span className="flex items-center gap-1 px-1 py-1">
                    <Trophy className="h-3.5 w-3.5 text-neutral-200" />
                    <span className="h-1.5 flex-1 rounded bg-neutral-600" />
                </span>
            </div>
            <div className="flex flex-1 flex-col justify-between bg-neutral-900 p-3">
                <div>
                    <p className="text-xs font-semibold text-yellow-300">Round 1 — Top 3 Selection</p>
                    <div className="mt-1.5 h-5 rounded border border-neutral-600 bg-neutral-800" />
                </div>
                <span className="inline-flex w-fit items-center gap-1 rounded bg-yellow-400 px-2 py-1 text-xs font-semibold text-black">
                    <Plus className="h-3 w-3" /> Add
                </span>
            </div>
        </div>
    );
}

// One theme: click the preview to use it everywhere. `themeProps` scopes its colors.
function ThemeCard({ name, note, description, selected, saving, onUse, themeProps, usedBy = [], defaultCount = 0, actions }) {
    const usage = [
        selected && `The default: pages outside events${defaultCount ? ` and ${defaultCount} event${defaultCount === 1 ? "" : "s"} on Default` : ""}.`,
        usedBy.length > 0 && `Used by ${usedBy.join(", ")}.`,
    ].filter(Boolean);

    return (
        <li {...themeProps} className={cn("flex flex-col gap-3 rounded-xl border-2 bg-neutral-900 p-4", selected ? "border-yellow-400" : "border-neutral-700")}>
            <button
                type="button"
                onClick={onUse}
                aria-pressed={selected}
                aria-label={`Make ${name} the default theme`}
                disabled={saving}
                className="flex flex-col gap-3 rounded-lg text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-900 disabled:cursor-wait"
            >
                <Preview />
                <span className="flex w-full items-start justify-between gap-2">
                    <span className="min-w-0">
                        <span className="block truncate font-semibold text-white">
                            {name}
                            {note && <span className="ml-2 text-xs font-normal text-gray-400">{note}</span>}
                        </span>
                        {description && <span className="block text-sm text-gray-400">{description}</span>}
                    </span>
                    {selected ? (
                        <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-yellow-400 px-2 py-0.5 text-xs font-semibold text-black">
                            <Check className="h-3.5 w-3.5" aria-hidden="true" /> Default
                        </span>
                    ) : saving ? (
                        <span className="shrink-0 text-xs text-gray-300">Applying…</span>
                    ) : null}
                </span>
            </button>
            {usage.length > 0 && <p className="text-xs text-gray-300">{usage.join(" ")}</p>}
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </li>
    );
}

function ColorField({ id, label, help, value, onChange, error }) {
    const errorId = `${id}-error`;
    return (
        <div>
            <label htmlFor={id} className="text-sm font-medium text-gray-300">{label}</label>
            <p className="text-xs text-gray-400">{help}</p>
            <div className="mt-1 flex items-center gap-2">
                <input
                    type="color"
                    aria-label={`${label} picker`}
                    value={HEX.test(value) ? value : "#000000"}
                    onChange={(e) => onChange(e.target.value)}
                    className="h-11 w-14 cursor-pointer rounded-lg border border-neutral-600 bg-neutral-800 p-1"
                />
                <input
                    id={id}
                    className={cn(input, "w-32 font-mono uppercase", error && "border-red-500")}
                    value={value}
                    maxLength={7}
                    spellCheck={false}
                    onChange={(e) => {
                        const v = e.target.value.trim();
                        onChange(v.startsWith("#") || v === "" ? v : `#${v}`);
                    }}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? errorId : undefined}
                />
            </div>
            {error && <p id={errorId} role="alert" className="mt-1 text-sm text-red-400">{error}</p>}
        </div>
    );
}

// Create or edit a custom theme: a name and two colors, previewed live, checked for
// readability as you pick (the server checks the same rules).
function ThemeEditor({ theme, onClose }) {
    const [data, setData] = useState({
        name: theme?.name ?? "",
        accent: theme?.accent ?? "#facc15",
        surface: theme?.surface ?? "#171717",
        apply: !theme,
    });
    const [serverErrors, setServerErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const set = (key) => (value) => {
        setData((d) => ({ ...d, [key]: value }));
        setServerErrors((e) => ({ ...e, [key]: undefined }));
    };

    const valid = HEX.test(data.accent) && HEX.test(data.surface);
    const problems = valid ? themeProblems(data.accent, data.surface) : {};
    const errors = {
        name: serverErrors.name,
        accent: serverErrors.accent ?? (!HEX.test(data.accent) ? "Use a color like #FACC15." : problems.accent),
        surface: serverErrors.surface ?? (!HEX.test(data.surface) ? "Use a color like #171717." : problems.surface),
        form: serverErrors.form,
    };

    const submit = (e) => {
        e.preventDefault();
        if (!data.name.trim()) return setServerErrors({ name: "Give the theme a name." });
        if (errors.accent || errors.surface || processing) return;

        setProcessing(true);
        const url = theme ? route("admin.themes.update", theme.id) : route("admin.themes.store");
        send(theme ? "put" : "post", url, { ...data, name: data.name.trim() }, theme ? "Theme saved." : "Theme created.", onClose, {
            onError: (message, fieldErrors) => setServerErrors(Object.keys(fieldErrors).length ? fieldErrors : { form: message }),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-4 bg-neutral-900 p-6 text-white">
            <h2 className="text-lg font-semibold">{theme ? `Edit “${theme.name}”` : "Create a theme"}</h2>

            <div>
                <label htmlFor="theme-name" className="text-sm font-medium text-gray-300">Name</label>
                <input
                    id="theme-name"
                    className={cn(input, "mt-1 block w-full", errors.name && "border-red-500")}
                    value={data.name}
                    maxLength={40}
                    placeholder="e.g. School Colors"
                    onChange={(e) => set("name")(e.target.value)}
                    aria-invalid={Boolean(errors.name)}
                    aria-describedby={errors.name ? "theme-name-error" : undefined}
                />
                {errors.name && <p id="theme-name-error" role="alert" className="mt-1 text-sm text-red-400">{errors.name}</p>}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <ColorField id="theme-accent" label="Accent" help="Buttons, highlights, the active menu item." value={data.accent} onChange={set("accent")} error={errors.accent} />
                <ColorField id="theme-surface" label="Background" help="Pages and panels. Keep it dark." value={data.surface} onChange={set("surface")} error={errors.surface} />
            </div>

            <div>
                <p className="mb-1 text-sm font-medium text-gray-300">Preview</p>
                {valid ? (
                    <div style={themeVariables(data.accent, data.surface)}>
                        <Preview />
                    </div>
                ) : (
                    <p className="text-sm text-gray-400">Pick both colors to see a preview.</p>
                )}
            </div>

            {!theme && (
                <label className="flex min-h-11 items-center gap-2 text-sm text-gray-200">
                    <input
                        type="checkbox"
                        checked={data.apply}
                        onChange={(e) => set("apply")(e.target.checked)}
                        className="h-5 w-5 rounded border-neutral-600 bg-neutral-800 text-yellow-400 focus:ring-yellow-400"
                    />
                    Make it the default theme now
                </label>
            )}

            {errors.form && <p role="alert" className="text-sm text-red-400">{errors.form}</p>}

            <div className="flex justify-end gap-2">
                <button type="button" onClick={onClose} className="min-h-11 rounded-lg border border-neutral-600 bg-neutral-800 px-4 font-semibold hover:bg-neutral-700">Cancel</button>
                <button
                    type="submit"
                    disabled={processing || Boolean(errors.accent || errors.surface)}
                    className="min-h-11 rounded-lg bg-yellow-400 px-4 font-semibold text-black hover:bg-yellow-300 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {processing ? "Saving…" : theme ? "Save" : "Create theme"}
                </button>
            </div>
        </form>
    );
}

// The color theme for the whole app (admins, judges, every device): a preset or one an admin made.
export default function Theme({ presets, customThemes = [], defaultCount = 0 }) {
    const { theme: current } = usePage().props;
    const [saving, setSaving] = useState(null);
    const [error, setError] = useState(null);
    const [editing, setEditing] = useState(null); // null | "new" | custom theme
    const [askDelete, deleteDialog] = useConfirmDelete();

    const use = (key, label) => {
        if (key === current || saving) return;
        setSaving(key);
        setError(null);
        send("put", route("admin.theme.update"), { theme: key }, `${label} is now the default theme.`, null, {
            onError: (message) => setError(message),
            onFinish: () => setSaving(null),
        });
    };

    return (
        <PageLayout>
            <div className="p-4 text-white md:p-8">
                <div className="mb-2 flex items-center gap-2">
                    <Palette className="h-6 w-6 text-yellow-400" aria-hidden="true" />
                    <h2 className="text-xl font-bold">Theme</h2>
                </div>
                <p className="mb-6 max-w-2xl text-sm text-gray-300">
                    Each event picks its own theme in <span className="font-semibold text-white">Events → Set up → Settings</span>.
                    Click a theme below to make it the <span className="font-semibold text-white">default</span>: it's used on the
                    login page, the events list, and by every event left on “Default”. Judges' open screens update within a few seconds.
                    Printed results keep their usual white print style.
                </p>

                {error && (
                    <p role="alert" className="mb-4 max-w-2xl rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-300">
                        {error}
                    </p>
                )}

                <section aria-labelledby="custom-themes-heading" className="mb-10">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <h3 id="custom-themes-heading" className="font-semibold text-yellow-300">Your themes</h3>
                        <button
                            type="button"
                            onClick={() => setEditing("new")}
                            className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-yellow-400 px-4 font-semibold text-black hover:bg-yellow-300"
                        >
                            <Plus className="h-5 w-5" aria-hidden="true" /> Create theme
                        </button>
                    </div>
                    {customThemes.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-neutral-700 p-6 text-sm text-gray-400">
                            No themes of your own yet. Click “Create theme” to pick an accent and a background color; events can then use it.
                        </p>
                    ) : (
                        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {customThemes.map((t) => {
                                const isDefault = t.key === current;
                                const deleteBlock = isDefault
                                    ? "It's the default: pick another default to delete it."
                                    : t.usedBy.length > 0
                                      ? "Events use it: pick another theme for them to delete it."
                                      : null;
                                return (
                                    <ThemeCard
                                        key={t.id}
                                        name={t.name}
                                        description={`${t.accent.toUpperCase()} on ${t.surface.toUpperCase()}`}
                                        selected={isDefault}
                                        saving={saving === t.key}
                                        onUse={() => use(t.key, t.name)}
                                        themeProps={{ style: t.vars }}
                                        usedBy={t.usedBy}
                                        defaultCount={defaultCount}
                                        actions={
                                            <>
                                                <button type="button" onClick={() => setEditing(t)} className={smallButton} aria-label={`Edit ${t.name}`}>
                                                    <Pencil className="h-4 w-4" aria-hidden="true" /> Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    disabled={Boolean(deleteBlock)}
                                                    title={deleteBlock ?? undefined}
                                                    onClick={() =>
                                                        askDelete({
                                                            title: `Delete the “${t.name}” theme?`,
                                                            description: "It's removed from your themes. This can't be undone.",
                                                            confirmLabel: "Delete theme",
                                                            url: route("admin.themes.destroy", t.id),
                                                            success: "Theme deleted.",
                                                        })
                                                    }
                                                    className={cn(smallButton, "border-red-500/50 text-red-300")}
                                                    aria-label={`Delete ${t.name}`}
                                                >
                                                    <Trash2 className="h-4 w-4" aria-hidden="true" /> Delete
                                                </button>
                                                {deleteBlock && <span className="self-center text-xs text-gray-400">{deleteBlock}</span>}
                                            </>
                                        }
                                    />
                                );
                            })}
                        </ul>
                    )}
                </section>

                <section aria-labelledby="preset-themes-heading">
                    <h3 id="preset-themes-heading" className="mb-3 font-semibold text-yellow-300">Presets</h3>
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {presets.map((preset, i) => (
                            <ThemeCard
                                key={preset.key}
                                name={preset.label}
                                note={i === 0 ? "Default" : null}
                                description={preset.description}
                                selected={preset.key === current}
                                saving={saving === preset.key}
                                onUse={() => use(preset.key, preset.label)}
                                themeProps={{ "data-theme": preset.key }}
                                usedBy={preset.usedBy}
                                defaultCount={defaultCount}
                            />
                        ))}
                    </ul>
                </section>
            </div>

            {editing && (
                <Modal show onClose={() => setEditing(null)} maxWidth="lg">
                    <ThemeEditor key={editing === "new" ? "new" : editing.id} theme={editing === "new" ? null : editing} onClose={() => setEditing(null)} />
                </Modal>
            )}
            {deleteDialog}
        </PageLayout>
    );
}
