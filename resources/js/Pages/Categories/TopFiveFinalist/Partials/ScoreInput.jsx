"use client";

import React, { useState } from "react";
import { motion } from "motion/react";

const ScoreInput = ({ value, onChange, max, disabled = false }) => {
    const [hovered, setHovered] = useState(false);
    const [focused, setFocused] = useState(false);

    const movingMap = {
        TOP: "radial-gradient(60% 120% at 50% 0%, rgba(50,117,248,0.8) 0%, rgba(50,117,248,0) 100%)",
        LEFT: "radial-gradient(50% 100% at 0% 50%, rgba(50,117,248,0.8) 0%, rgba(50,117,248,0) 100%)",
        BOTTOM: "radial-gradient(60% 120% at 50% 100%, rgba(50,117,248,0.8) 0%, rgba(50,117,248,0) 100%)",
        RIGHT: "radial-gradient(50% 100% at 100% 50%, rgba(50,117,248,0.8) 0%, rgba(50,117,248,0) 100%)",
    };

    const highlight =
        "radial-gradient(80% 200% at 50% 50%, rgba(50,117,248,0.8) 0%, rgba(50,117,248,0) 100%)";

    const handleChange = (e) => {
        let val = e.target.value;
        if (val === "") {
            onChange("");
            return;
        }
        val = parseFloat(val);
        if (isNaN(val)) return;
        val = Math.min(val, max);
        val = Math.round(val * 10) / 10;
        onChange(val);
    };

    return (
        <div className="w-full flex flex-col items-center mt-2">
            <label className="text-sm text-gray-400 mb-1">Score</label>
            <div
                className="p-[2px] rounded-full w-28 relative"
                onMouseEnter={() => setHovered(true)}
                onMouseLeave={() => setHovered(false)}
            >
                <motion.div
                    className="absolute inset-0 rounded-full"
                    style={{ filter: "blur(3px)" }}
                    animate={{
                        background:
                            hovered || focused
                                ? [
                                      movingMap.TOP,
                                      movingMap.LEFT,
                                      movingMap.BOTTOM,
                                      movingMap.RIGHT,
                                      movingMap.TOP,
                                  ]
                                : highlight,
                    }}
                    transition={{
                        ease: "linear",
                        duration: 4,
                        repeat: Infinity,
                    }}
                />
                {!focused && (
                    <div className="absolute inset-0 rounded-full bg-neutral-900/20" />
                )}
                <input
                    type="number"
                    min={0}
                    max={max}
                    value={value ?? ""}
                    onChange={handleChange}
                    onFocus={() => setFocused(true)}
                    onBlur={() => setFocused(false)}
                    placeholder={`0.0 / ${max}`}
                    disabled={disabled}
                    className="relative z-10 w-full text-center px-4 py-2 rounded-full bg-neutral-900 text-white focus:outline-none focus:bg-neutral-800 appearance-none [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none disabled:opacity-50 disabled:cursor-not-allowed"
                />
            </div>
        </div>
    );
};

export default ScoreInput;
