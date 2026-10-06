// Shrinks a candidate photo in the browser into the three sizes CandidatePhoto.jsx
// uses, so uploads stay small and the server needs no image tools:
//   photo  JPEG, fits inside 1365x2048 (never enlarged)
//   card   WebP, 480px wide
//   thumb  WebP, 96px square, center-cropped
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
    const cardWidth = Math.min(480, width);
    const side = Math.min(width, height);

    const photo = await toBlob(draw(bitmap, Math.round(width * fit), Math.round(height * fit)), "image/jpeg", 0.82);
    const card = await toBlob(draw(bitmap, cardWidth, Math.round((height * cardWidth) / width)), "image/webp", 0.75);
    const thumb = await toBlob(
        draw(bitmap, 96, 96, { x: (width - side) / 2, y: (height - side) / 2, size: side }),
        "image/webp",
        0.7
    );
    bitmap.close?.();

    // Some browsers silently fall back to PNG when they can't encode WebP.
    if (!photo || card?.type !== "image/webp" || thumb?.type !== "image/webp") {
        throw new Error(NO_WEBP);
    }

    return {
        photo: new File([photo], "photo.jpg", { type: "image/jpeg" }),
        card: new File([card], "card.webp", { type: "image/webp" }),
        thumb: new File([thumb], "thumb.webp", { type: "image/webp" }),
    };
}
