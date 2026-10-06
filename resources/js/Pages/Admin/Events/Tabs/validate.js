// Checks done in the browser before saving a setup change, matching the server's
// rules (CategoryController / GroupController). Each returns an error message or null.

export function checkName(value, { what = "name", max = 80 } = {}) {
    const name = String(value ?? "").trim();
    if (!name) return `Enter a ${what}.`;
    if (name.length > max) return `Keep the ${what} to ${max} characters or fewer.`;
    return null;
}

export function checkMaxPoints(value) {
    const raw = String(value ?? "").trim();
    if (!raw) return "Enter the max points.";
    const points = Number(raw);
    if (!Number.isFinite(points)) return "Max points must be a number.";
    if (points < 0.01 || points > 999.99) return "Max points must be between 0.01 and 999.99.";
    if (!/^\d+(\.\d{1,2})?$/.test(raw)) return "Use at most 2 decimal places.";
    return null;
}
