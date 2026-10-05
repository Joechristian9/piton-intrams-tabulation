// Run with: npm run test:js  (Node's built-in test runner, no extra packages)
import test from "node:test";
import assert from "node:assert/strict";
import { isLocked, canSubmit, scoresToSubmit } from "../../resources/js/lib/scoreSheet.js";

const saved = (id, score) => ({ id, existing_score: score });
const fresh = (id) => ({ id, existing_score: null });

test("a tab where every candidate has a saved score is locked", () => {
    assert.equal(isLocked([saved(1, 20), saved(2, 18)]), true);
    assert.equal(isLocked([saved(1, 20), fresh(3)]), false);
    assert.equal(isLocked([]), false);
});

test("a candidate added after submitting can be scored and submitted", () => {
    // Final review: drafts are cleared after a submit, so saved scores must count as filled.
    const tab = [saved(1, 20), saved(2, 18), fresh(3)];

    assert.equal(canSubmit(tab, {}), false);
    assert.equal(canSubmit(tab, { 3: 22.5 }), true);
    assert.deepEqual(scoresToSubmit(tab, { 3: 22.5 }), { 3: 22.5 });
});

test("a fresh tab needs every candidate filled and sends them all", () => {
    const tab = [fresh(1), fresh(2)];

    assert.equal(canSubmit(tab, { 1: 10 }), false);
    assert.equal(canSubmit(tab, { 1: 10, 2: "" }), false);
    assert.equal(canSubmit(tab, { 1: 10, 2: 0 }), true);
    assert.deepEqual(scoresToSubmit(tab, { 1: 10, 2: 0 }), { 1: 10, 2: 0 });
});

test("nothing can be submitted from a locked or closed round", () => {
    assert.equal(canSubmit([saved(1, 20)], {}), false);
    assert.equal(canSubmit([fresh(1)], { 1: 5 }, { roundClosed: true }), false);
});
