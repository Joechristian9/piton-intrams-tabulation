// A candidate's display name: "First Last Suffix" (e.g. "Juan Dela Cruz Jr.").
export default function candidateName(c) {
    return [c?.first_name, c?.last_name, c?.name_suffix].filter(Boolean).join(" ");
}
