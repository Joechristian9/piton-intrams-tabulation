import { useReducedMotion } from "motion/react";
import StarsBackground from "@/Components/backgrounds/stars";

const GRADIENT = "bg-[radial-gradient(ellipse_at_bottom,_#0a192f_0%,_#000_70%)]";

// Starry backdrop with a faint HUD grid, shared by the landing, login and signed-in
// home pages. Shows a static gradient when the user prefers reduced motion.
// `contained`: fill the nearest positioned parent instead of the whole window (the
// signed-in home, so the stars stay inside the content area, not over the sidebar).
export default function PitonBackdrop({ contained = false }) {
    const reduceMotion = useReducedMotion();

    return (
        <div className={`pointer-events-none ${contained ? "absolute" : "fixed"} inset-0 z-0`} aria-hidden="true">
            {reduceMotion ? (
                <div className={`h-full w-full ${GRADIENT}`} />
            ) : (
                <StarsBackground
                    starColor="#ffffff"
                    pointerEvents={false}
                    className={`h-full w-full ${GRADIENT}`}
                />
            )}
            <div className="absolute inset-0 bg-[linear-gradient(rgba(250,204,21,0.06)_1px,transparent_1px),linear-gradient(90deg,rgba(96,165,250,0.06)_1px,transparent_1px)] bg-[size:56px_56px] [mask-image:radial-gradient(ellipse_at_center,black_20%,transparent_70%)]" />
        </div>
    );
}
