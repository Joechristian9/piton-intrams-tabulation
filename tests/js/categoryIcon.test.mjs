import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import categoryIcon, { CATEGORY_ICONS, autoIconKey } from "../../resources/js/lib/categoryIcon.js";

const serverKeys = JSON.parse(readFileSync(new URL("../../resources/js/lib/categoryIcons.json", import.meta.url), "utf8"));

test("the picker offers exactly the icon keys the server accepts", () => {
    assert.deepEqual(Object.keys(CATEGORY_ICONS).sort(), [...serverKeys].sort());
});

test("auto icons come from the name, the first matching keyword winning", () => {
    assert.equal(autoIconKey("Swim Wear"), "waves");
    assert.equal(autoIconKey("Casual Interview"), "mic");
    assert.equal(autoIconKey("Over-all Appeal / X-factor"), "sparkles");
    assert.equal(autoIconKey("Something Else", 0), "star");
    assert.equal(autoIconKey("Something Else", 1), "flag");
});

test("a picked icon wins over the name; an unknown key falls back to auto", () => {
    assert.equal(categoryIcon("Swim Wear", 0, "crown"), CATEGORY_ICONS.crown.icon);
    assert.equal(categoryIcon("Swim Wear", 0, null), CATEGORY_ICONS.waves.icon);
    assert.equal(categoryIcon("Swim Wear", 0, "nope"), CATEGORY_ICONS.waves.icon);
});
