import React, { useState } from "react";
import ConfirmDialog from "@/Components/ConfirmDialog";
import send from "./request";

// Delete after a confirmation dialog. `ask({ title, description, url, success })`
// opens it; a failed delete keeps it open with the reason so the admin can retry.
export default function useConfirmDelete() {
    const [pending, setPending] = useState(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState(null);

    const ask = (request) => {
        setError(null);
        setPending(request);
    };

    const confirm = () => {
        setProcessing(true);
        setError(null);
        send("delete", pending.url, {}, pending.success, () => setPending(null), {
            onError: (message) => setError(message),
            onFinish: () => setProcessing(false),
        });
    };

    const dialog = pending && (
        <ConfirmDialog
            title={pending.title}
            description={pending.description}
            confirmLabel={pending.confirmLabel ?? "Delete"}
            error={error}
            processing={processing}
            onCancel={() => setPending(null)}
            onConfirm={confirm}
        />
    );

    return [ask, dialog];
}
