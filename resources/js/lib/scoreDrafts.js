// Unsubmitted judge scores are saved in localStorage (per category and judge)
// so they survive switching tabs, opening another category, or refreshing.
const storageKey = (category, judgeId) => `score-draft:${category}:${judgeId}`;

export const loadDraftScores = (category, judgeId) => {
    try {
        return (
            JSON.parse(localStorage.getItem(storageKey(category, judgeId))) ??
            {}
        );
    } catch {
        return {};
    }
};

export const saveDraftScores = (category, judgeId, scores) => {
    try {
        localStorage.setItem(
            storageKey(category, judgeId),
            JSON.stringify(scores)
        );
    } catch {
        // Storage unavailable (e.g. private mode); scores stay in memory only.
    }
};

export const clearDraftScores = (category, judgeId, candidateIds) => {
    const scores = loadDraftScores(category, judgeId);
    candidateIds.forEach((id) => delete scores[id]);
    saveDraftScores(category, judgeId, scores);
};
