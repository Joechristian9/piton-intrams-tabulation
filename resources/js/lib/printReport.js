// Confidentiality on printed results: the score columns are numbered ("Judge 1")
// instead of named, and the signature lines show names only, alphabetically, so
// a printout doesn't reveal which judge gave which scores.

export const judgeColumnLabel = (number) => `Judge ${number}`;

export const signatureNames = (judges) =>
    judges
        .map((judge) => judge.name.replaceAll("_", " "))
        .sort((a, b) => a.localeCompare(b, undefined, { sensitivity: "base" }));
