// The developer credit is set by the developer: it reads "© <year> joe-dev", lives only
// in Components/DeveloperCredit.jsx, and the landing, login and home pages show it.
// A change to the name, or a page dropping the credit, fails this test on purpose.
import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "../..");
const read = (file) => readFileSync(resolve(root, "resources/js", file), "utf8");

test("the developer credit names joe-dev", () => {
    assert.match(read("Components/DeveloperCredit.jsx"), /export const DEVELOPER = "joe-dev";/);
});

test("landing, login and home pages show the shared credit", () => {
    for (const file of ["Pages/Welcome.jsx", "Layouts/GuestLayout.jsx", "Pages/Home.jsx"]) {
        const src = read(file);
        assert.match(src, /import DeveloperCredit from "@\/Components\/DeveloperCredit";/, file);
        assert.match(src, /<DeveloperCredit\b/, file);
    }
});
