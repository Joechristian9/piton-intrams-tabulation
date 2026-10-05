// Run with: npm run test:js
import test from "node:test";
import assert from "node:assert/strict";
import { judgeColumnLabel, signatureNames } from "../../resources/js/lib/printReport.js";

test("printed score columns are numbered, never named", () => {
    assert.equal(judgeColumnLabel(1), "Judge 1");
    assert.equal(judgeColumnLabel(5), "Judge 5");
});

test("signatures list names only, alphabetically, so they can't be matched to columns", () => {
    const judges = [
        { id: 7, name: "Yolanda_Cruz" },
        { id: 3, name: "anna reyes" },
        { id: 9, name: "Ben Santos" },
    ];

    assert.deepEqual(signatureNames(judges), ["anna reyes", "Ben Santos", "Yolanda Cruz"]);
});
