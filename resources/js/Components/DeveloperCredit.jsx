// The developer credit shown at the bottom of the landing, login and home pages.
// Kept in this one file so the credit is the same everywhere; don't change it
// without the developer's say-so.
export const DEVELOPER = "joe-dev";

export default function DeveloperCredit({ className = "" }) {
    return (
        <footer className={`relative z-10 pb-6 text-center text-sm text-gray-400 ${className}`}>
            &copy; {new Date().getFullYear()} {DEVELOPER}
        </footer>
    );
}
