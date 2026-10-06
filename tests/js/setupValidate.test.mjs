import { test } from "node:test";
import assert from "node:assert/strict";
import { checkMaxPoints, checkName } from "../../resources/js/Pages/Admin/Events/Tabs/validate.js";

test("names must not be blank and must fit the server's limit", () => {
    assert.equal(checkName("  Swim Wear "), null);
    assert.equal(checkName("   ", { what: "category name" }), "Enter a category name.");
    assert.equal(checkName("x".repeat(81)), "Keep the name to 80 characters or fewer.");
    assert.equal(checkName("x".repeat(61), { max: 60 }), "Keep the name to 60 characters or fewer.");
});

test("max points: a number from 0.01 to 999.99 with at most 2 decimals", () => {
    for (const ok of ["10", "25.5", "0.01", "999.99", 40]) assert.equal(checkMaxPoints(ok), null, String(ok));
    assert.equal(checkMaxPoints(""), "Enter the max points.");
    assert.equal(checkMaxPoints("abc"), "Max points must be a number.");
    assert.equal(checkMaxPoints("0"), "Max points must be between 0.01 and 999.99.");
    assert.equal(checkMaxPoints("1000"), "Max points must be between 0.01 and 999.99.");
    assert.equal(checkMaxPoints("-5"), "Max points must be between 0.01 and 999.99.");
    assert.equal(checkMaxPoints("10.555"), "Use at most 2 decimal places.");
});
