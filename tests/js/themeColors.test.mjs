import { test } from "node:test";
import assert from "node:assert/strict";
import { themeProblems, themeVariables } from "../../resources/js/lib/themeColors.js";

// The same numbers are pinned in tests/Feature/CustomThemeTest.php, so the editor's
// live preview and the colors the server applies stay identical.
test("two colors become full shade scales anchored on the picked colors", () => {
    const v = themeVariables("#3b82f6", "#0f172a");
    assert.equal(Object.keys(v).length, 33);
    assert.equal(v["--accent-400"], "59 130 246"); // the picked accent
    assert.equal(v["--accent2-400"], "59 130 246");
    assert.equal(v["--accent-50"], "239 245 254");
    assert.equal(v["--accent-900"], "19 42 79");
    assert.equal(v["--surface-900"], "15 23 42"); // the picked background
    assert.equal(v["--surface-800"], "32 39 57");
    assert.equal(v["--surface-950"], "8 13 23");
});

test("readability rules", () => {
    assert.deepEqual(themeProblems("#3b82f6", "#0f172a"), {});
    assert.match(themeProblems("#1e3a8a", "#0f172a").accent, /too dark/);
    assert.match(themeProblems("#facc15", "#9ca3af").surface, /too light/);
    assert.match(themeProblems("#8a8a8a", "#333333").accent, /too close/);
    assert.deepEqual(themeProblems("#zzzzzz", "#000000"), {}); // format errors are reported separately
});
