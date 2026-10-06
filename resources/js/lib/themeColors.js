// A custom theme's two colors → the CSS variables the app reads (--accent-*,
// --accent2-*, --surface-*, as "r g b"; see tailwind.config.js), plus readability
// checks. Mirrors App\Support\ThemeColors (the server applies and validates the
// same way); both read themeScale.json. tests/js/themeColors.test.mjs pins it.
import scale from "./themeScale.json" with { type: "json" };

export const HEX = /^#[0-9a-f]{6}$/i;

const rgb = (hex) => {
    const n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
};

// Toward white (weight > 0) or black (weight < 0).
const mix = (color, weight) => {
    const target = weight > 0 ? 255 : 0;
    return color.map((c) => Math.round(c + (target - c) * Math.abs(weight)));
};

const luminance = (color) => {
    const [r, g, b] = color.map((c) => {
        const v = c / 255;
        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

const contrast = (l1, l2) => (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);

/** { "--accent-400": "250 204 21", ... } for an accent and a background color. */
export function themeVariables(accent, surface) {
    const vars = {};
    for (const [role, hex] of [["accent", accent], ["accent2", accent], ["surface", surface]]) {
        const color = rgb(hex);
        for (const [shade, weight] of Object.entries(scale[role === "surface" ? "surface" : "accent"])) {
            vars[`--${role}-${shade}`] = mix(color, weight).join(" ");
        }
    }
    return vars;
}

/** Why the pair would be hard to read, by field ({ accent?, surface? }); same rules as the server. */
export function themeProblems(accent, surface) {
    if (!HEX.test(accent) || !HEX.test(surface)) return {};
    const a = luminance(rgb(accent));
    const s = luminance(rgb(surface));
    const problems = {};

    if (contrast(a, 0) < 4.5) {
        problems.accent = "This accent is too dark: black text on its buttons wouldn't be readable. Pick a lighter color.";
    }
    if (s > 0.04) {
        problems.surface = "This background is too light for the app's white text. Pick a darker color.";
    }
    if (!problems.accent && !problems.surface && contrast(a, s) < 4.5) {
        problems.accent = "The accent and background are too close, so highlighted text would not stand out. Pick a brighter accent or a darker background.";
    }
    return problems;
}
