import { useRef, useState } from "react";
import { Head, useForm } from "@inertiajs/react";
import { AlertCircle, Eye, EyeOff, Loader2, Lock, LogIn, Mail } from "lucide-react";
import GuestLayout from "@/Layouts/GuestLayout";

const inputClass = (hasError) =>
    `block min-h-12 w-full rounded-lg border bg-neutral-800/80 pl-10 text-base text-white placeholder-gray-500 transition-colors duration-200 focus:border-yellow-400 focus:outline-none focus:ring-2 focus:ring-yellow-400/40 ${
        hasError ? "border-red-500" : "border-neutral-600"
    }`;

function FieldError({ id, message }) {
    if (!message) return null;

    return (
        <p id={id} role="alert" className="mt-2 flex items-start gap-1.5 text-sm text-red-400">
            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
            {message}
        </p>
    );
}

export default function Login({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: "",
        password: "",
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);
    const emailRef = useRef(null);

    const submit = (e) => {
        e.preventDefault();

        post(route("login"), {
            onFinish: () => reset("password"),
            // Wrong credentials are reported on the login field; take the user there.
            onError: () => emailRef.current?.focus(),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <div className="text-center">
                <h1 className="text-2xl font-bold tracking-tight text-white">
                    Welcome back
                </h1>
                <p className="mt-2 text-sm text-gray-400">
                    Log in to the PITON Tabulation System.
                </p>
            </div>

            {status && (
                <div
                    role="status"
                    className="mt-6 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-300"
                >
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-8 space-y-5" noValidate>
                <div>
                    <label htmlFor="login" className="block text-sm font-medium text-gray-300">
                        Username or email
                    </label>
                    <div className="relative mt-2">
                        <Mail
                            className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                            aria-hidden="true"
                        />
                        <input
                            ref={emailRef}
                            id="login"
                            type="text"
                            name="login"
                            autoComplete="username"
                            autoCapitalize="none"
                            spellCheck={false}
                            autoFocus
                            required
                            placeholder="e.g. pageant26-judge1"
                            value={data.login}
                            onChange={(e) => setData("login", e.target.value)}
                            aria-invalid={errors.login ? true : undefined}
                            aria-describedby={errors.login ? "login-error" : undefined}
                            className={`${inputClass(errors.login)} pr-3`}
                        />
                    </div>
                    <FieldError id="login-error" message={errors.login} />
                </div>

                <div>
                    <label htmlFor="password" className="block text-sm font-medium text-gray-300">
                        Password
                    </label>
                    <div className="relative mt-2">
                        <Lock
                            className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                            aria-hidden="true"
                        />
                        <input
                            id="password"
                            type={showPassword ? "text" : "password"}
                            name="password"
                            autoComplete="current-password"
                            required
                            value={data.password}
                            onChange={(e) => setData("password", e.target.value)}
                            aria-invalid={errors.password ? true : undefined}
                            aria-describedby={errors.password ? "password-error" : undefined}
                            className={`${inputClass(errors.password)} pr-12`}
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword((s) => !s)}
                            aria-label={showPassword ? "Hide password" : "Show password"}
                            aria-pressed={showPassword}
                            className="absolute right-0.5 top-1/2 grid h-11 w-11 -translate-y-1/2 cursor-pointer place-items-center rounded-md text-gray-400 transition-colors duration-200 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-300"
                        >
                            {showPassword ? (
                                <EyeOff className="h-5 w-5" aria-hidden="true" />
                            ) : (
                                <Eye className="h-5 w-5" aria-hidden="true" />
                            )}
                        </button>
                    </div>
                    <FieldError id="password-error" message={errors.password} />
                </div>

                <label className="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-gray-300">
                    <input
                        type="checkbox"
                        name="remember"
                        checked={data.remember}
                        onChange={(e) => setData("remember", e.target.checked)}
                        className="h-5 w-5 rounded border-neutral-600 bg-neutral-800 text-yellow-400 focus:ring-2 focus:ring-yellow-400 focus:ring-offset-neutral-900"
                    />
                    Remember me on this device
                </label>

                <button
                    type="submit"
                    disabled={processing}
                    aria-busy={processing}
                    className="inline-flex min-h-12 w-full cursor-pointer items-center justify-center gap-2 rounded-full bg-yellow-400 px-6 text-base font-semibold text-black shadow-[0_0_24px_rgba(250,204,21,0.3)] transition duration-200 hover:bg-yellow-300 hover:shadow-[0_0_32px_rgba(250,204,21,0.5)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-300 focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-900 active:scale-[0.98] disabled:cursor-wait disabled:opacity-80"
                >
                    {processing ? (
                        <>
                            <Loader2 className="h-5 w-5 motion-safe:animate-spin" aria-hidden="true" />
                            Logging in…
                        </>
                    ) : (
                        <>
                            <LogIn className="h-5 w-5" aria-hidden="true" />
                            Log in
                        </>
                    )}
                </button>

                <p className="text-center text-sm text-gray-400">
                    Forgot your password? Ask the organizer to reset it.
                </p>
            </form>
        </GuestLayout>
    );
}
