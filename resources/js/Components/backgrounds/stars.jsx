"use client";
import React from "react";
import { motion, useMotionValue, useSpring } from "framer-motion";
import { cn } from "@/lib/utils";

function generateStars(count, starColor) {
    const shadows = [];
    for (let i = 0; i < count; i++) {
        const x = Math.floor(Math.random() * 4000) - 2000;
        const y = Math.floor(Math.random() * 4000) - 2000;
        shadows.push(`${x}px ${y}px ${starColor}`);
    }
    return shadows.join(", ");
}

function StarLayer({ count = 1000, size = 1, transition, starColor }) {
    const [boxShadow, setBoxShadow] = React.useState("");

    React.useEffect(() => {
        setBoxShadow(generateStars(count, starColor));
    }, [count, starColor]);

    return (
        <motion.div
            animate={{ y: [0, -2000] }}
            transition={transition}
            className="absolute top-0 left-0 w-full h-[2000px]"
        >
            <div
                className="absolute bg-transparent rounded-full"
                style={{ width: size, height: size, boxShadow }}
            />
            <div
                className="absolute bg-transparent rounded-full top-[2000px]"
                style={{ width: size, height: size, boxShadow }}
            />
        </motion.div>
    );
}

export default function StarsBackground({
    children,
    className,
    factor = 0.05,
    speed = 50,
    starColor = "#fff",
    pointerEvents = true,
}) {
    const offsetX = useMotionValue(1);
    const offsetY = useMotionValue(1);

    const springX = useSpring(offsetX, { stiffness: 50, damping: 20 });
    const springY = useSpring(offsetY, { stiffness: 50, damping: 20 });

    const handleMouseMove = (e) => {
        const centerX = window.innerWidth / 2;
        const centerY = window.innerHeight / 2;
        offsetX.set(-(e.clientX - centerX) * factor);
        offsetY.set(-(e.clientY - centerY) * factor);
    };

    return (
        <div
            onMouseMove={handleMouseMove}
            className={cn(
                "relative size-full overflow-hidden",
                "bg-[radial-gradient(ellipse_at_bottom,_#262626_0%,_#000_100%)]",
                className
            )}
        >
            <motion.div
                style={{ x: springX, y: springY }}
                className={cn({ "pointer-events-none": !pointerEvents })}
            >
                <StarLayer
                    count={1000}
                    size={1}
                    transition={{
                        repeat: Infinity,
                        duration: speed,
                        ease: "linear",
                    }}
                    starColor={starColor}
                />
                <StarLayer
                    count={400}
                    size={2}
                    transition={{
                        repeat: Infinity,
                        duration: speed * 2,
                        ease: "linear",
                    }}
                    starColor={starColor}
                />
                <StarLayer
                    count={200}
                    size={3}
                    transition={{
                        repeat: Infinity,
                        duration: speed * 3,
                        ease: "linear",
                    }}
                    starColor={starColor}
                />
            </motion.div>
            {children}
        </div>
    );
}
