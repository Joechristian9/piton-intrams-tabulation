import { router } from "@inertiajs/react";
import { toast } from "sonner";

/** A readable message for a failed request that isn't a validation error. */
export function requestErrorMessage(status) {
    if (!status) return "Couldn't reach the server. Check the connection and try again.";
    if (status === 404) return "That item no longer exists. Refresh the page.";
    if (status === 403) return "You don't have permission to do that.";
    if (status === 419) return "Your session expired. Refresh the page and try again.";
    if (status === 413) return "That upload is too large.";
    if (status === 429) return "Too many changes at once. Wait a moment and try again.";
    return "Something went wrong on the server. Try again.";
}

// Send a setup change. Validation errors, server errors (404, 500, an expired
// session) and a lost connection all end in `onError(message, errors)` — by
// default a toast — instead of Inertia's error page. `then` runs after success
// (Inertia cancels a visit when another starts, so dependent requests must be
// chained, not fired together); `onFinish` runs either way.
export default function send(method, url, data = {}, success, then, { onError, onFinish } = {}) {
    const fail = (message, errors = {}) => (onError ? onError(message, errors) : toast.error(message));

    // Only for this request: trap non-Inertia responses and network failures.
    const stopInvalid = router.on("invalid", (event) => {
        event.preventDefault();
        fail(requestErrorMessage(event.detail.response?.status));
    });
    const stopException = router.on("exception", (event) => {
        event.preventDefault();
        fail(requestErrorMessage(null));
    });

    router.visit(url, {
        method,
        data,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (success) toast.success(success);
            then?.();
        },
        onError: (errors) => fail(Object.values(errors)[0] ?? "Couldn't save that change.", errors),
        onFinish: () => {
            stopInvalid();
            stopException();
            onFinish?.();
        },
    });
}
