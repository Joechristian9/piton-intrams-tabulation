import "@fontsource/figtree/400.css";
import "@fontsource/figtree/500.css";
import "@fontsource/figtree/600.css";
import "../css/app.css";
import "./bootstrap";

import { createInertiaApp, router } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob("./Pages/**/*.jsx")
        ),
    setup({ el, App, props }) {
        // The color theme (`theme` shared prop) lives on <html data-theme>; a custom
        // theme's colors (`themeVars`) are inline CSS variables on <html>. The server sets
        // both on first load; this keeps them current after an admin changes the theme.
        let appliedVars = {};
        const applyTheme = (page) => {
            const { theme, themeVars } = page?.props ?? {};
            if (!theme) return;
            const html = document.documentElement;
            html.dataset.theme = theme;
            const vars = themeVars ?? {};
            for (const name of Object.keys(appliedVars)) {
                if (!(name in vars)) html.style.removeProperty(name);
            }
            for (const [name, value] of Object.entries(vars)) html.style.setProperty(name, value);
            appliedVars = vars;
        };
        applyTheme(props.initialPage); // so a later switch knows which inline variables to remove
        router.on("navigate", (e) => applyTheme(e.detail.page));
        router.on("success", (e) => applyTheme(e.detail.page));

        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: "#4B5563",
    },
});
