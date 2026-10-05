import React, { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import { ImagePlus, Pencil, Trash2, UserPlus } from "lucide-react";
import Modal from "@/Components/Modal";
import CandidatePhoto from "@/Components/CandidatePhoto";
import { resizePhoto } from "@/lib/photoResize";
import send from "./request";

const field =
    "mt-1 block w-full min-h-11 rounded-lg border-neutral-600 bg-neutral-800 text-white focus:border-yellow-400 focus:ring-yellow-400";

function CandidateForm({ event, groups, candidate, onClose }) {
    const [data, setData] = useState({
        candidate_number: candidate?.candidate_number ?? "",
        first_name: candidate?.first_name ?? "",
        last_name: candidate?.last_name ?? "",
        course: candidate?.course ?? "",
        group_id: candidate?.group_id ?? groups[0]?.id ?? "",
    });
    const [photos, setPhotos] = useState(null);
    const [preview, setPreview] = useState(null);
    const [photoError, setPhotoError] = useState(null);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const set = (key) => (e) => setData((d) => ({ ...d, [key]: e.target.value }));

    const pickPhoto = async (e) => {
        const file = e.target.files?.[0];
        setPhotoError(null);
        if (!file) return;
        try {
            const resized = await resizePhoto(file);
            setPhotos(resized);
            setPreview(URL.createObjectURL(resized.card));
        } catch (err) {
            setPhotos(null);
            setPreview(null);
            setPhotoError(err.message);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        if (!candidate && !photos) {
            setPhotoError("Add a photo.");
            return;
        }

        const payload = {
            ...data,
            ...(photos ? { photo: photos.photo, photo_card: photos.card, photo_thumb: photos.thumb } : {}),
            ...(candidate ? { _method: "PUT" } : {}),
        };
        const url = candidate ? route("admin.candidates.update", candidate.id) : route("admin.candidates.store", event.id);

        setProcessing(true);
        router.post(url, payload, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success(candidate ? "Candidate saved." : "Candidate added.");
                onClose();
            },
            onError: setErrors,
            onFinish: () => setProcessing(false),
        });
    };

    const err = (key) => errors[key] && <p role="alert" className="mt-1 text-sm text-red-400">{errors[key]}</p>;

    return (
        <form onSubmit={submit} className="space-y-4 bg-neutral-900 p-6 text-white">
            <h2 className="text-lg font-semibold">{candidate ? "Edit candidate" : "Add candidate"}</h2>

            <div className="flex items-start gap-4">
                <div className="h-36 w-24 shrink-0 overflow-hidden rounded-lg border border-neutral-700 bg-neutral-800">
                    {preview ? (
                        <img src={preview} alt="" className="h-full w-full object-cover" />
                    ) : candidate ? (
                        <CandidatePhoto path={candidate.profile_img} alt="" className="h-full w-full object-cover" />
                    ) : (
                        <div className="grid h-full place-items-center text-gray-500">
                            <ImagePlus className="h-8 w-8" aria-hidden="true" />
                        </div>
                    )}
                </div>
                <div className="flex-1">
                    <label htmlFor="cand-photo" className="text-sm font-medium text-gray-300">
                        Photo {candidate && <span className="text-gray-400">(leave empty to keep)</span>}
                    </label>
                    <input id="cand-photo" type="file" accept="image/jpeg,image/png,image/webp" onChange={pickPhoto} className="mt-1 block w-full text-sm text-gray-300 file:mr-3 file:min-h-11 file:rounded-lg file:border-0 file:bg-neutral-700 file:px-4 file:text-white" />
                    <p className="mt-1 text-xs text-gray-400">JPG or PNG. It's resized here before uploading.</p>
                    {(photoError || errors.photo || errors.photo_card || errors.photo_thumb) && (
                        <p role="alert" className="mt-1 text-sm text-red-400">{photoError ?? errors.photo ?? errors.photo_card ?? errors.photo_thumb}</p>
                    )}
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <label htmlFor="cand-group" className="text-sm font-medium text-gray-300">Group</label>
                    <select id="cand-group" className={field} value={data.group_id} onChange={set("group_id")} required>
                        {groups.map((g) => <option key={g.id} value={g.id}>{g.name}</option>)}
                    </select>
                    {err("group_id")}
                </div>
                <div>
                    <label htmlFor="cand-number" className="text-sm font-medium text-gray-300">Number</label>
                    <input id="cand-number" type="number" min={1} className={field} value={data.candidate_number} onChange={set("candidate_number")} required />
                    {err("candidate_number")}
                </div>
                <div>
                    <label htmlFor="cand-first" className="text-sm font-medium text-gray-300">First name</label>
                    <input id="cand-first" className={field} value={data.first_name} onChange={set("first_name")} required />
                    {err("first_name")}
                </div>
                <div>
                    <label htmlFor="cand-last" className="text-sm font-medium text-gray-300">Last name</label>
                    <input id="cand-last" className={field} value={data.last_name} onChange={set("last_name")} required />
                    {err("last_name")}
                </div>
                <div className="sm:col-span-2">
                    <label htmlFor="cand-course" className="text-sm font-medium text-gray-300">Course / description (optional)</label>
                    <input id="cand-course" className={field} value={data.course ?? ""} onChange={set("course")} />
                    {err("course")}
                </div>
            </div>

            <div className="flex justify-end gap-2">
                <button type="button" onClick={onClose} className="min-h-11 rounded-lg border border-neutral-600 bg-neutral-800 px-4 font-semibold hover:bg-neutral-700">Cancel</button>
                <button type="submit" disabled={processing} className="min-h-11 rounded-lg bg-yellow-400 px-4 font-semibold text-black hover:bg-yellow-300 disabled:opacity-50">
                    {processing ? "Saving…" : candidate ? "Save" : "Add candidate"}
                </button>
            </div>
        </form>
    );
}

// An event's candidates with their photos, by group.
export default function Candidates({ event, groups, candidates }) {
    const [editing, setEditing] = useState(null); // null | "new" | candidate

    if (groups.length === 0) {
        return <p className="text-gray-300">Add at least one group first (Groups tab).</p>;
    }

    const remove = (c) => {
        if (window.confirm(`Delete ${c.first_name} ${c.last_name}? Their photo is deleted too.`)) {
            send("delete", route("admin.candidates.destroy", c.id), {}, "Candidate deleted.");
        }
    };

    return (
        <div className="space-y-6 text-white">
            <button type="button" onClick={() => setEditing("new")} className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-yellow-400 px-4 font-semibold text-black hover:bg-yellow-300">
                <UserPlus className="h-5 w-5" aria-hidden="true" /> Add candidate
            </button>

            {groups.map((g) => {
                const members = candidates.filter((c) => c.group_id === g.id);
                return (
                    <section key={g.id}>
                        <h3 className="mb-2 font-semibold text-yellow-300">{g.name} ({members.length})</h3>
                        {members.length === 0 ? (
                            <p className="text-sm text-gray-400">No candidates yet.</p>
                        ) : (
                            <ul className="space-y-2">
                                {members.map((c) => (
                                    <li key={c.id} className="flex items-center gap-3 rounded-lg border border-neutral-700 p-2">
                                        <CandidatePhoto path={c.profile_img} size="thumb" alt="" width={40} height={40} className="h-10 w-10 rounded-full object-cover" />
                                        <span className="w-10 text-gray-400">#{c.candidate_number}</span>
                                        <span className="min-w-0 flex-1 truncate">
                                            {c.first_name} {c.last_name}
                                            {c.course && <span className="block truncate text-xs text-gray-400">{c.course}</span>}
                                        </span>
                                        <button type="button" onClick={() => setEditing(c)} aria-label={`Edit ${c.first_name}`} className="grid h-11 w-11 place-items-center rounded-lg border border-neutral-600 bg-neutral-800 hover:bg-neutral-700">
                                            <Pencil className="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => remove(c)}
                                            disabled={c.hasScores}
                                            title={c.hasScores ? "This candidate has scores, so they can't be deleted." : undefined}
                                            aria-label={`Delete ${c.first_name}`}
                                            className="grid h-11 w-11 place-items-center rounded-lg border border-red-500/50 bg-neutral-800 text-red-300 hover:bg-neutral-700 disabled:cursor-not-allowed disabled:opacity-30"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                );
            })}

            {editing && (
                <Modal show onClose={() => setEditing(null)} maxWidth="lg">
                    <CandidateForm
                        key={editing === "new" ? "new" : editing.id}
                        event={event}
                        groups={groups}
                        candidate={editing === "new" ? null : editing}
                        onClose={() => setEditing(null)}
                    />
                </Modal>
            )}
        </div>
    );
}
