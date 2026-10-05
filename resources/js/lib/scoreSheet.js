// Submit rules for one group's tab on the judge scoring page. Candidates carry
// `existing_score` (saved on the server, or null); drafts are the judge's typed,
// unsaved scores keyed by candidate id.

const hasSaved = (c) => c.existing_score != null;
const hasDraft = (c, drafts) => drafts[c.id] !== undefined && drafts[c.id] !== "";

// Every candidate already has a saved score: nothing left to do in this tab.
export const isLocked = (candidates) => candidates.length > 0 && candidates.every(hasSaved);

// Submit is possible when every candidate is either saved or typed in, and at
// least one is new (e.g. a candidate added after the judge's first submit).
export const canSubmit = (candidates, drafts, { roundClosed = false } = {}) =>
    !roundClosed &&
    !isLocked(candidates) &&
    candidates.every((c) => hasSaved(c) || hasDraft(c, drafts));

// Only the not-yet-saved candidates are sent; saved scores stay as they are.
export const scoresToSubmit = (candidates, drafts) =>
    Object.fromEntries(candidates.filter((c) => !hasSaved(c)).map((c) => [c.id, drafts[c.id]]));
