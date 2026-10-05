import { router } from "@inertiajs/react";
import { toast } from "sonner";

// Send a setup change; the first validation error (if any) becomes a toast.
// `then` runs after success (Inertia cancels a visit when another starts, so
// dependent requests must be chained, not fired together).
export default function send(method, url, data = {}, success, then) {
    router.visit(url, {
        method,
        data,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (success) toast.success(success);
            then?.();
        },
        onError: (errors) => toast.error(Object.values(errors)[0] ?? "Couldn't save that change."),
    });
}
