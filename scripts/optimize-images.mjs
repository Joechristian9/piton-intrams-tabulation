// Shrinks candidate photos and builds the WebP versions CandidatePhoto.jsx expects.
// Run after adding or replacing photos:  npm run images
//
// For every public/candidates/<gender>/<n>.<jpg|jpeg> it:
//   - re-encodes the original in place, at most 1365x2048 (keeps the file name)
//   - writes <n>.webp        (480px wide, for the judges' cards)
//   - writes <n>-thumb.webp  (96px square, for avatars in tables and dialogs)
// It also makes small WebP copies of the logos shown on every page.
import { readdir, readFile, writeFile, stat } from "node:fs/promises";
import path from "node:path";
import sharp from "sharp";

const PUBLIC = path.resolve(import.meta.dirname, "..", "public");
const kb = (bytes) => `${Math.round(bytes / 1024)} KB`;

async function optimizePhoto(file) {
    const dir = path.dirname(file);
    const base = path.basename(file, path.extname(file));
    const input = await readFile(file); // read fully first: the original is overwritten
    const before = input.length;

    // .rotate() applies the camera's EXIF orientation before the metadata is dropped.
    const original = await sharp(input)
        .rotate()
        .resize(1365, 2048, { fit: "inside", withoutEnlargement: true })
        .jpeg({ quality: 82, mozjpeg: true })
        .toBuffer();
    if (original.length < before) await writeFile(file, original);

    await sharp(input).rotate().resize({ width: 480 }).webp({ quality: 75 })
        .toFile(path.join(dir, `${base}.webp`));
    await sharp(input).rotate().resize(96, 96, { fit: "cover", position: "attention" })
        .webp({ quality: 70 })
        .toFile(path.join(dir, `${base}-thumb.webp`));

    console.log(`${path.relative(PUBLIC, file)}: ${kb(before)} -> ${kb(Math.min(original.length, before))}`);
}

for (const gender of ["female", "male"]) {
    const dir = path.join(PUBLIC, "candidates", gender);
    for (const name of await readdir(dir)) {
        if (/\.jpe?g$/i.test(name)) await optimizePhoto(path.join(dir, name));
    }
}

// Logos: [source, output, size in px]
const logos = [
    ["isu-logo.png", "isu-logo.webp", 96],
    ["PITON LOGO.png", "piton-logo.webp", 384],
];
for (const [src, out, size] of logos) {
    const output = path.join(PUBLIC, out);
    await sharp(path.join(PUBLIC, src))
        .resize(size, size, { fit: "inside", withoutEnlargement: true })
        .webp({ quality: 85 })
        .toFile(output);
    console.log(`${out}: ${kb((await stat(output)).size)}`);
}
