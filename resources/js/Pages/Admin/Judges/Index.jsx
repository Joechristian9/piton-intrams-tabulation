"use client";

import React, { useState } from "react";
import { useForm } from "@inertiajs/react";
import { toast } from "sonner";
import { Pencil, UserPlus } from "lucide-react";
import PageLayout from "@/Layouts/PageLayout";
import Modal from "@/Components/Modal";
import InputLabel from "@/Components/InputLabel";
import TextInput from "@/Components/TextInput";
import InputError from "@/Components/InputError";
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableCell,
    TableHead,
} from "@/components/ui/table";

const inputClass =
    "mt-1 block w-full bg-neutral-800 border-neutral-600 text-white placeholder-neutral-500";

const JudgeFormModal = ({ judge, show, onClose }) => {
    const isEdit = Boolean(judge);
    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm({
            name: judge?.name ?? "",
            email: judge?.email ?? "",
            password: "",
            password_confirmation: "",
        });

    const close = () => {
        reset();
        clearErrors();
        onClose();
    };

    const submit = (e) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(isEdit ? "Judge updated." : "Judge added.");
                close();
            },
        };

        if (isEdit) {
            put(route("admin.judges.update", judge.id), options);
        } else {
            post(route("admin.judges.store"), options);
        }
    };

    return (
        <Modal show={show} onClose={close} maxWidth="md">
            {/* The modal renders outside the page's dark wrapper. */}
            <form onSubmit={submit} className="bg-neutral-900 text-white p-6">
                <h2 className="text-lg font-semibold">
                    {isEdit ? "Edit Judge" : "Add New Judge"}
                </h2>

                <div className="mt-5">
                    <InputLabel htmlFor="name" value="Name" className="!text-neutral-300" />
                    <TextInput
                        id="name"
                        value={data.name}
                        onChange={(e) => setData("name", e.target.value)}
                        className={inputClass}
                        isFocused
                        required
                    />
                    <InputError message={errors.name} className="mt-1" />
                </div>

                <div className="mt-4">
                    <InputLabel
                        htmlFor="email"
                        value="Email (used to log in)"
                        className="!text-neutral-300"
                    />
                    <TextInput
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData("email", e.target.value)}
                        className={inputClass}
                        required
                    />
                    <InputError message={errors.email} className="mt-1" />
                </div>

                <div className="mt-4">
                    <InputLabel
                        htmlFor="password"
                        value={isEdit ? "New Password" : "Password"}
                        className="!text-neutral-300"
                    />
                    <TextInput
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData("password", e.target.value)}
                        className={inputClass}
                        placeholder={
                            isEdit ? "Leave blank to keep current password" : ""
                        }
                        autoComplete="new-password"
                        required={!isEdit}
                    />
                    <InputError message={errors.password} className="mt-1" />
                </div>

                <div className="mt-4">
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Confirm Password"
                        className="!text-neutral-300"
                    />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData("password_confirmation", e.target.value)
                        }
                        className={inputClass}
                        autoComplete="new-password"
                        required={!isEdit || data.password !== ""}
                    />
                </div>

                <div className="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={close}
                        className="px-4 py-2 rounded-lg border border-neutral-600 bg-neutral-800 hover:bg-neutral-700 font-semibold"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 font-semibold disabled:opacity-50"
                    >
                        {isEdit ? "Save Changes" : "Add Judge"}
                    </button>
                </div>
            </form>
        </Modal>
    );
};

const JudgesIndex = ({ judges = [] }) => {
    // null = closed, "new" = add form, or the judge being edited.
    const [editing, setEditing] = useState(null);

    return (
        <PageLayout>
            <div className="p-4 md:p-8">
                <div className="flex items-center justify-between mb-6">
                    <h2 className="text-white text-xl font-bold">
                        Judges Management
                    </h2>
                    <button
                        type="button"
                        onClick={() => setEditing("new")}
                        className="flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold"
                    >
                        <UserPlus className="h-5 w-5" />
                        Add New Judge
                    </button>
                </div>

                <Table className="bg-neutral-900 text-white border border-gray-700">
                    <TableHeader>
                        <TableRow>
                            <TableHead>#</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {judges.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="text-center text-neutral-400 py-8"
                                >
                                    No judges yet. Click "Add New Judge" to
                                    create one.
                                </TableCell>
                            </TableRow>
                        ) : (
                            judges.map((judge, idx) => (
                                <TableRow key={judge.id}>
                                    <TableCell>{idx + 1}</TableCell>
                                    <TableCell className="font-medium">
                                        {judge.name}
                                    </TableCell>
                                    <TableCell className="text-neutral-300">
                                        {judge.email}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <button
                                            type="button"
                                            onClick={() => setEditing(judge)}
                                            className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-neutral-600 bg-neutral-800 hover:bg-neutral-700 text-sm"
                                        >
                                            <Pencil className="h-4 w-4" />
                                            Edit
                                        </button>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            {editing && (
                <JudgeFormModal
                    key={editing === "new" ? "new" : editing.id}
                    judge={editing === "new" ? null : editing}
                    show
                    onClose={() => setEditing(null)}
                />
            )}
        </PageLayout>
    );
};

export default JudgesIndex;
