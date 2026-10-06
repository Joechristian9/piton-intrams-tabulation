import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";
import plugin from "tailwindcss/plugin";

// Color themes (resources/js/lib/themes.json, picked by an admin on the Theme page).
// The app's gold (`yellow-*`, `amber-*`) and charcoal (`neutral-*`) classes read CSS
// variables, which each theme sets under `[data-theme="<key>"]` on <html>. The first
// theme (PITON Gold) maps them back to Tailwind's own yellow/amber/neutral.
const palette = require("tailwindcss/colors");
const themes = require("./resources/js/lib/themes.json");
const SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
const ROLES = { accent: "yellow", accent2: "amber", surface: "neutral" };

const channels = (hex) => {
    const n = parseInt(hex.slice(1), 16);
    return `${(n >> 16) & 255} ${(n >> 8) & 255} ${n & 255}`;
};
const scale = (role) => Object.fromEntries(SHADES.map((s) => [s, `rgb(var(--${role}-${s}) / <alpha-value>)`]));
const themeVariables = (theme) =>
    Object.fromEntries(
        Object.keys(ROLES).flatMap((role) => {
            const name = theme[role] ?? theme.accent; // accent2 defaults to the accent
            return SHADES.map((s) => [`--${role}-${s}`, channels(palette[name][s])]);
        }),
    );
const themeColors = plugin(({ addBase }) =>
    addBase(
        Object.fromEntries(
            themes.map((theme, i) => [
                i === 0 ? `:root, [data-theme="${theme.key}"]` : `[data-theme="${theme.key}"]`,
                themeVariables(theme),
            ]),
        ),
    ),
);

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.jsx",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Figtree", ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                lg: "var(--radius)",
                md: "calc(var(--radius) - 2px)",
                sm: "calc(var(--radius) - 4px)",
            },
            colors: {
                // Themeable (see themeColors above).
                yellow: scale("accent"),
                amber: scale("accent2"),
                neutral: scale("surface"),
                background: "hsl(var(--background))",
                foreground: "hsl(var(--foreground))",
                card: {
                    DEFAULT: "hsl(var(--card))",
                    foreground: "hsl(var(--card-foreground))",
                },
                popover: {
                    DEFAULT: "hsl(var(--popover))",
                    foreground: "hsl(var(--popover-foreground))",
                },
                primary: {
                    DEFAULT: "hsl(var(--primary))",
                    foreground: "hsl(var(--primary-foreground))",
                },
                secondary: {
                    DEFAULT: "hsl(var(--secondary))",
                    foreground: "hsl(var(--secondary-foreground))",
                },
                muted: {
                    DEFAULT: "hsl(var(--muted))",
                    foreground: "hsl(var(--muted-foreground))",
                },
                accent: {
                    DEFAULT: "hsl(var(--accent))",
                    foreground: "hsl(var(--accent-foreground))",
                },
                destructive: {
                    DEFAULT: "hsl(var(--destructive))",
                    foreground: "hsl(var(--destructive-foreground))",
                },
                border: "hsl(var(--border))",
                input: "hsl(var(--input))",
                ring: "hsl(var(--ring))",
                chart: {
                    1: "hsl(var(--chart-1))",
                    2: "hsl(var(--chart-2))",
                    3: "hsl(var(--chart-3))",
                    4: "hsl(var(--chart-4))",
                    5: "hsl(var(--chart-5))",
                },
            },
        },
    },

    plugins: [forms, require("tailwindcss-animate"), themeColors],
};
