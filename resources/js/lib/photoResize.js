// Shrinks a candidate photo in the browser into the sizes CandidatePhoto.jsx uses, so
// uploads stay small and the server needs no image tools:
//   photo       JPEG, fits inside 1365x2048 (never enlarged) — for browsers without WebP
//   card        WebP, 360px wide (phones / high-DPR screens; cards show ~200–310px wide)
//   cardSmall   WebP, 240px wide (desktop 1x screens)
//   thumb       WebP, 96px square, center-cropped (phones / high-DPR avatars)
//   thumbSmall  WebP, 48px square (desktop 1x avatars)
// Keep in step with scripts/optimize-images.mjs (the original pageant's photos).
const CARD_WIDTH = 360;
const CARD_QUALITY = 0.72;
const CARD_SMALL_WIDTH = 240;

export const UNSUPPORTED = "This photo format isn't supported. Use a JPG or PNG.";
export const NO_WEBP = "This browser can't create WebP images. Use Chrome or Edge to upload photos.";

function toBlob(canvas, type, quality) {
    return new Promise((resolve) => canvas.toBlob(resolve, type, quality));
}

function draw(bitmap, width, height, crop = null) {
    const canvas = document.createElement("canvas");
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext("2d");
    ctx.imageSmoothingQuality = "high";
    if (crop) {
        ctx.drawImage(bitmap, crop.x, crop.y, crop.size, crop.size, 0, 0, width, height);
    } else {
        ctx.drawImage(bitmap, 0, 0, width, height);
    }
    return canvas;
}

export async function resizePhoto(file) {
    let bitmap;
    try {
        // Applies the camera's EXIF rotation; fails for formats the browser can't
        // decode (e.g. HEIC from iPhones on Windows).
        bitmap = await createImageBitmap(file, { imageOrientation: "from-image" });
    } catch {
        throw new Error(UNSUPPORTED);
    }

    const { width, height } = bitmap;
    const fit = Math.min(1, 1365 / width, 2048 / height);
    const cardWidth = Math.min(CARD_WIDTH, width);
    const side = Math.min(width, height);

    const photo = await toBlob(draw(bitmap, Math.round(width * fit), Math.round(height * fit)), "image/jpeg", 0.82);
    const smallWidth = Math.min(CARD_SMALL_WIDTH, width);
    const square = { x: (width - side) / 2, y: (height - side) / 2, size: side };

    const card = await toBlob(draw(bitmap, cardWidth, Math.round((height * cardWidth) / width)), "image/webp", CARD_QUALITY);
    const cardSmall = await toBlob(draw(bitmap, smallWidth, Math.round((height * smallWidth) / width)), "image/webp", 0.7);
    const thumb = await toBlob(draw(bitmap, 96, 96, square), "image/webp", 0.7);
    const thumbSmall = await toBlob(draw(bitmap, 48, 48, square), "image/webp", 0.7);
    bitmap.close?.();

    // Some browsers silently fall back to PNG when they can't encode WebP.
    if (!photo || [card, cardSmall, thumb, thumbSmall].some((blob) => blob?.type !== "image/webp")) {
        throw new Error(NO_WEBP);
    }

    return {
        photo: new File([photo], "photo.jpg", { type: "image/jpeg" }),
        card: new File([card], "card.webp", { type: "image/webp" }),
        cardSmall: new File([cardSmall], "card-sm.webp", { type: "image/webp" }),
        thumb: new File([thumb], "thumb.webp", { type: "image/webp" }),
        thumbSmall: new File([thumbSmall], "thumb-sm.webp", { type: "image/webp" }),
    };
}
