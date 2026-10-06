// Optimized candidate photo. The local photos (public/candidates/<gender>/<n>.jpg or
// .JPEG, ~220 KB each, at most 1365x2048) have WebP versions next to them: <n>.webp
// (480px wide, ~25 KB) for cards and <n>-thumb.webp (96px square, ~2 KB) for avatars.
// `npm run images` regenerates them after photos are added or replaced. Browsers
// that support WebP get those; anything else, or any other path, uses the original.
// Photos uploaded per event (uploads/candidates/<event>/<uuid>.jpg) get the same two
// WebP sizes from the admin's browser when they're added.
const LOCAL_PHOTO = /^(candidates\/(?:male|female))\/(\d+)\.jpe?g$/i;
const UPLOADED_PHOTO = /^(uploads\/candidates\/\d+)\/([0-9a-f-]+)\.jpg$/i;

export default function CandidatePhoto({ path, size = "card", alt, className, ...props }) {
    // Stored paths look like "candidates/female/1.jpg" (sometimes "admin/..." or "/...").
    const clean = (path || "").replace(/^\/+/, "").replace(/^admin\//, "");
    const src = clean ? `/${clean}` : "/default-avatar.png";

    const match = clean.match(LOCAL_PHOTO) ?? clean.match(UPLOADED_PHOTO);
    const webp = match
        ? `/${match[1]}/${match[2]}${size === "thumb" ? "-thumb" : ""}.webp`
        : null;

    const img = (
        <img
            src={src}
            alt={alt}
            loading="lazy"
            decoding="async"
            className={className}
            {...props}
        />
    );

    if (!webp) return img;

    return (
        // `contents` keeps <picture> out of the layout, so the <img> styles apply as before.
        <picture className="contents">
            <source srcSet={webp} type="image/webp" />
            {img}
        </picture>
    );
}
