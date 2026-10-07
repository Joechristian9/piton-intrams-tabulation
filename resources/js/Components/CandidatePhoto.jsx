import { cn } from "@/lib/utils";

// Optimized candidate photo, at the resolution its screen needs. Every photo — the
// original pageant's (public/candidates/<gender>/<n>.jpg) and uploads
// (uploads/candidates/<event>/<uuid>.jpg) — has WebP versions next to it:
//   card:  <n>.webp 360px (~15 KB)   and <n>-sm.webp 240px (~7 KB)
//   thumb: <n>-thumb.webp 96px (~1.4 KB) and <n>-thumb-sm.webp 48px (~0.6 KB)
// Both sizes are listed in srcset with `sizes` (how wide the photo is shown), so the
// browser picks: desktop 1x screens get the small ones, phones and high-DPR screens
// the larger. `npm run images` makes them for the original photos; the admin's browser
// makes them for uploads (lib/photoResize.js). A missing file breaks the image. Browsers
// without WebP, or any other path, get the original.
const LOCAL_PHOTO = /^(candidates\/(?:male|female))\/(\d+)\.jpe?g$/i;
const UPLOADED_PHOTO = /^(uploads\/candidates\/\d+)\/([0-9a-f-]+)\.jpg$/i;

// How wide each kind is shown, for the browser to choose a file. Cards follow the judges'
// grid (CandidateGrid: 1 / 2 / 3 / 5 columns, minus the sidebar, gaps and card padding).
const SIZES = {
    card: "(min-width: 1024px) calc((100vw - 156px) / 5 - 32px), (min-width: 768px) calc((100vw - 124px) / 3 - 32px), (min-width: 640px) calc((100vw - 48px) / 2 - 32px), calc(100vw - 64px)",
    thumb: "40px",
};

const markLoaded = (e) => e.currentTarget.setAttribute("data-loaded", "");

// `priority`: for the few photos visible as the page opens (the first candidate
// cards) — loaded right away at high priority instead of lazily, so they appear first.
// `sizes`: override when a photo is shown at another size than usual (e.g. a preview).
export default function CandidatePhoto({ path, size = "card", alt, className, priority = false, sizes, ...props }) {
    // Stored paths look like "candidates/female/1.jpg" (sometimes "admin/..." or "/...").
    const clean = (path || "").replace(/^\/+/, "").replace(/^admin\//, "");
    // Candidates added without a photo have an empty path: show the silhouette placeholder.
    const src = clean ? `/${clean}` : "/candidate-placeholder.svg";

    const match = clean.match(LOCAL_PHOTO) ?? clean.match(UPLOADED_PHOTO);
    const stem = match ? `/${match[1]}/${match[2]}` : null;
    const srcSet = !stem
        ? null
        : size === "thumb"
          ? `${stem}-thumb-sm.webp 48w, ${stem}-thumb.webp 96w`
          : `${stem}-sm.webp 240w, ${stem}.webp 360w`;

    const img = (
        <img
            src={src}
            alt={alt}
            loading={priority ? "eager" : "lazy"}
            // Lowercase: React 18 passes it through as the HTML attribute.
            fetchpriority={priority ? "high" : undefined}
            decoding="async"
            // A shimmer in the photo's space until it arrives (app.css .photo-skeleton);
            // loading (or failing) sets data-loaded straight on the element, which ends
            // the animation without a React re-render.
            className={cn("photo-skeleton", className)}
            onLoad={markLoaded}
            onError={markLoaded}
            {...props}
        />
    );

    if (!srcSet) return img;

    return (
        // `contents` keeps <picture> out of the layout, so the <img> styles apply as before.
        <picture className="contents">
            <source srcSet={srcSet} sizes={sizes ?? SIZES[size] ?? SIZES.card} type="image/webp" />
            {img}
        </picture>
    );
}
