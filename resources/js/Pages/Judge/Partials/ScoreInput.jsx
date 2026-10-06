"use client";

import React from "react";

const ScoreInput = ({ value, onChange, max, disabled = false }) => {
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
            {/* The glow is pure CSS: still when idle; on hover or while typing a light
                circles the box (a spinning conic gradient, a cheap GPU transform). No
                React state, so hovering or focusing a box doesn't re-render it — a dozen
                animated, blurred gradients used to lag judges' phones. */}
            <div className="group p-[2px] rounded-full w-28 relative">
                <span className="absolute inset-0 overflow-hidden rounded-full" aria-hidden="true">
                    <span className="absolute inset-0 rounded-full bg-[radial-gradient(80%_200%_at_50%_50%,rgba(50,117,248,0.8)_0%,rgba(50,117,248,0)_100%)] transition-opacity duration-300 group-hover:opacity-0 group-focus-within:opacity-0" />
                    <span className="absolute left-1/2 top-1/2 aspect-square w-[150%] -translate-x-1/2 -translate-y-1/2 bg-[conic-gradient(from_0deg,rgba(50,117,248,0)_0deg,rgba(50,117,248,0.95)_70deg,rgba(50,117,248,0)_150deg)] opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-hover:animate-[spin_4s_linear_infinite] group-focus-within:opacity-100 group-focus-within:animate-[spin_4s_linear_infinite] motion-reduce:!animate-none" />
                </span>
                <span className="absolute inset-0 rounded-full bg-neutral-900/20 group-focus-within:hidden" aria-hidden="true" />
                <input
                    type="number"
                    min={0}
                    max={max}
                    value={value ?? ""}
                    onChange={handleChange}
                    placeholder={`0.0 / ${max}`}
                    disabled={disabled}
                    className="relative z-10 w-full text-center px-4 py-2 rounded-full bg-neutral-900 text-white focus:outline-none focus:bg-neutral-800 appearance-none [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none disabled:opacity-50 disabled:cursor-not-allowed"
                />
            </div>
        </div>
    );
};

export default ScoreInput;
