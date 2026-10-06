import { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";

/**
 * State for one password-confirmed action (start, close, delete…).
 * `ask({ url, method, title, … })` opens the dialog; `dialogProps` feeds
 * PasswordConfirmDialog. A wrong password keeps it open; other errors toast.
 */
export default function usePasswordAction() {
    const [action, setAction] = useState(null);
    const [error, setError] = useState(null);
    const [processing, setProcessing] = useState(false);

    const close = () => {
        setAction(null);
        setError(null);
    };

    const confirm = (password) => {
        setProcessing(true);
        setError(null);

        router.visit(action.url, {
            method: action.method ?? "post",
            data: { password },
            preserveScroll: true,
            onSuccess: () => {
                if (action.success) toast.success(action.success);
                close();
            },
            onError: (errors) => {
                if (errors.password) {
                    setError(errors.password);
                    return;
                }
                close();
                toast.error(errors.event ?? Object.values(errors)[0] ?? "Something went wrong.");
            },
            onFinish: () => setProcessing(false),
        });
    };

    return {
        ask: (next) => {
            setError(null);
            setAction(next);
        },
        dialogProps: action && {
            title: action.title,
            description: action.description,
            confirmLabel: action.confirmLabel,
            danger: action.danger,
            error,
            processing,
            onCancel: close,
            onConfirm: confirm,
        },
    };
}
