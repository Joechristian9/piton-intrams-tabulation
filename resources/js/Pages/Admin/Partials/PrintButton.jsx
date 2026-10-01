"use client";

import React from "react";
import { HoverBorderGradient } from "@/Components/ui/hover-border-gradient";
import html2pdf from "html2pdf.js";

const PrintButton = ({ title, tableRef, category }) => {
    const handlePrintPDF = () => {
        const element = tableRef.current;
        if (!element) return;

        const filename = `${(category || title).replace(/\s+/g, "_")}.pdf`;

        const heading = document.createElement("h2");
        heading.textContent = `${category || title}`;
        heading.style.textAlign = "center";
        heading.style.fontSize = "22px";
        heading.style.fontWeight = "bold";
        heading.style.marginBottom = "20px";
        heading.style.color = "black";

        element.insertBefore(heading, element.firstChild);

        // Generate PDF and force download
        html2pdf(element, {
            margin: 10,
            filename,
            html2canvas: { scale: 2 },
            jsPDF: { unit: "mm", format: "a4", orientation: "portrait" },
        }).then(() => {
            // Remove temporary heading after saving
            element.removeChild(heading);
        });
    };

    return (
        <HoverBorderGradient
            containerClassName="rounded-full"
            as="button"
            className="dark:bg-neutral-800 bg-white text-black dark:text-neutral-100 flex items-center space-x-2 px-12 py-1 text-lg font-semibold"
            onClick={handlePrintPDF}
        >
            <span>Print PDF</span>
        </HoverBorderGradient>
    );
};

export default PrintButton;
