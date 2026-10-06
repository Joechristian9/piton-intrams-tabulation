// Run with: npm run test:js
// A page component that is imported by another page gets folded into that page's
// bundle and loses its own entry in the Vite build manifest, so the server can't
// render it (500: "Unable to locate file in Vite manifest"). Pages may import shared
// code (Partials/, Tabs/, components, lib) but never another page.
import test from "node:test";
import assert from "node:assert/strict";
import { readdirSync, readFileSync, existsSync } from "node:fs";
import { join, dirname, resolve, relative, sep } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "../..");
const pagesDir = join(root, "resources/js/Pages");
const isShared = (file) => /[\\/](Partials|Tabs)[\\/]/.test(file);

const walk = (dir) =>
    readdirSync(dir, { withFileTypes: true }).flatMap((e) =>
        e.isDirectory() ? walk(join(dir, e.name)) : e.name.endsWith(".jsx") ? [join(dir, e.name)] : []
    );

const resolveImport = (from, spec) => {
    const base = spec.startsWith("@/") ? join(root, "resources/js", spec.slice(2)) : spec.startsWith(".") ? resolve(dirname(from), spec) : null;
    if (!base) return null;
    return [base, `${base}.jsx`, `${base}.js`].find((p) => existsSync(p) && p.endsWith(".jsx")) ?? null;
};

test("no page imports another page", () => {
    const pages = walk(pagesDir).filter((f) => !isShared(f));
    const offenders = [];

    for (const page of pages) {
        const source = readFileSync(page, "utf8");
        for (const [, spec] of source.matchAll(/^\s*import\s[^'"]*["']([^"']+)["']/gm)) {
            const target = resolveImport(page, spec);
            if (target && target.startsWith(pagesDir + sep) && !isShared(target) && target !== page) {
                offenders.push(`${relative(root, page)} imports ${relative(root, target)}`);
            }
        }
    }

    assert.deepEqual(offenders, []);
});
