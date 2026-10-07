// Shrinks candidate photos and builds the WebP versions CandidatePhoto.jsx expects.
// Run after adding or replacing photos:  npm run images
//
// For every public/candidates/<gender>/<n>.<jpg|jpeg> it:
//   - shrinks the original in place to at most 1365x2048, only if it's still large
//   - writes <n>.webp           (360px wide, judges' cards on phones / high-DPR screens)
//   - writes <n>-sm.webp        (240px wide, the cards on desktop 1x screens)
//   - writes <n>-thumb.webp     (96px square, avatars on phones / high-DPR screens)
//   - writes <n>-thumb-sm.webp  (48px square, avatars on desktop 1x screens)
// CandidatePhoto.jsx lists each pair in srcset; the browser picks by screen.
// It also makes small WebP copies of the logos shown on every page.
import { readdir, readFile, writeFile, stat } from "node:fs/promises";
import path from "node:path";
import sharp from "sharp";

const PUBLIC = path.resolve(import.meta.dirname, "..", "public");
const kb = (bytes) => `${Math.round(bytes / 1024)} KB`;

async function optimizePhoto(file) {
    const dir = path.dirname(file);
    const base = path.basename(file, path.extname(file));
    const input = await readFile(file); // read fully first: the original may be overwritten
    const before = input.length;

    // Only shrink an original that is still large: re-encoding a JPEG that was already
    // optimized loses a little quality every run, for almost no bytes.
    const { width, height } = await sharp(input).metadata();
    let after = before;
    if (width > 1365 || height > 2048 || before > 400 * 1024) {
        // .rotate() applies the camera's EXIF orientation before the metadata is dropped.
        const original = await sharp(input)
            .rotate()
            .resize(1365, 2048, { fit: "inside", withoutEnlargement: true })
            .jpeg({ quality: 82, mozjpeg: true })
            .toBuffer();
        if (original.length < before) {
            await writeFile(file, original);
            after = original.length;
        }
    }

    // Card: shown about 200–310px wide (h-72), so 360px is enough and about half the
    // bytes of 480px. Keep in step with CARD_WIDTH in resources/js/lib/photoResize.js.
    await sharp(input).rotate().resize({ width: 360 }).webp({ quality: 72 })
        .toFile(path.join(dir, `${base}.webp`));
    // Desktop (1x screens) gets the small ones through srcset in CandidatePhoto.jsx.
    await sharp(input).rotate().resize({ width: 240 }).webp({ quality: 70 })
        .toFile(path.join(dir, `${base}-sm.webp`));
    await sharp(input).rotate().resize(96, 96, { fit: "cover", position: "attention" })
        .webp({ quality: 70 })
        .toFile(path.join(dir, `${base}-thumb.webp`));
    await sharp(input).rotate().resize(48, 48, { fit: "cover", position: "attention" })
        .webp({ quality: 70 })
        .toFile(path.join(dir, `${base}-thumb-sm.webp`));

    console.log(`${path.relative(PUBLIC, file)}: ${kb(before)} -> ${kb(after)}`);
}

for (const gender of ["female", "male"]) {
    const dir = path.join(PUBLIC, "candidates", gender);
    for (const name of await readdir(dir)) {
        if (/\.jpe?g$/i.test(name)) await optimizePhoto(path.join(dir, name));
    }
}

// Logos: [source, output, size in px]. The 64px PITON logo is the sidebar's (shown at
// 32px, sharp on 2x screens); the 384px one is for the landing and login pages.
const logos = [
    ["isu-logo.png", "isu-logo.webp", 96],
    ["PITON LOGO.png", "piton-logo.webp", 384],
    ["PITON LOGO.png", "piton-logo-64.webp", 64],
];
for (const [src, out, size] of logos) {
    const output = path.join(PUBLIC, out);
    await sharp(path.join(PUBLIC, src))
        .resize(size, size, { fit: "inside", withoutEnlargement: true })
        .webp({ quality: 85 })
        .toFile(output);
    console.log(`${out}: ${kb((await stat(output)).size)}`);
}
