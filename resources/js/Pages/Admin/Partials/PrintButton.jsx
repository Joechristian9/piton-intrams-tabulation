"use client";

import React, { useState } from "react";
import { HoverBorderGradient } from "@/Components/ui/hover-border-gradient";

// Print-only styles: white page, compact rows so a full table plus the
// signature lines fit on one landscape A4 page.
const REPORT_CSS = `
.pdf-report { width: 277mm; padding-bottom: 24px; background: #fff; color: #000; font-family: Figtree, Arial, sans-serif; }
.pdf-report h1 { text-align: center; font-size: 20px; font-weight: 700; margin: 0 0 2px; }
.pdf-report .pdf-meta { text-align: center; font-size: 11px; color: #555; margin-bottom: 12px; }
.pdf-report table { width: 100%; border-collapse: collapse; font-size: 11px; }
.pdf-report caption { display: none; }
.pdf-report th, .pdf-report td {
    border: 1px solid #999 !important; color: #000 !important; background: #fff !important;
    padding: 4px 6px !important; height: auto !important;
}
.pdf-report th { background: #e5e5e5 !important; font-weight: 700 !important; }
.pdf-report tr.pdf-top td { background: #fff1c2 !important; font-weight: 700; }
.pdf-report img { width: 20px !important; height: 20px !important; }
.pdf-signatures { margin-top: 36px; }
.pdf-signatures-title { font-size: 12px; font-weight: 700; margin-bottom: 8px; }
.pdf-signature-list { display: flex; flex-wrap: wrap; justify-content: space-around; gap: 28px 16px; }
.pdf-signature { width: 170px; text-align: center; font-size: 11px; }
.pdf-signature-name { margin-top: 34px; border-top: 1px solid #000; padding-top: 4px; font-weight: 700; text-transform: uppercase; }
.pdf-signature-role { color: #444; line-height: 1.5; }
`;

const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
};

// Builds a standalone white report: title, the results table, and one
// signature line per judge.
const buildReport = (table, reportTitle, judges) => {
    const report = el("div", "pdf-report");
    report.appendChild(el("style", null, REPORT_CSS));
    report.appendChild(el("h1", null, reportTitle));
    report.appendChild(
        el("div", "pdf-meta", `Printed on ${new Date().toLocaleString()}`)
    );

    const tableCopy = table.cloneNode(true);
    // Keep the rank 1 highlight from the on-screen table.
    tableCopy.querySelectorAll("tr").forEach((row) => {
        if (row.className.includes("bg-yellow")) row.classList.add("pdf-top");
    });
    report.appendChild(tableCopy);

    if (judges.length > 0) {
        const signatures = el("div", "pdf-signatures");
        signatures.appendChild(
            el("div", "pdf-signatures-title", "Judges' Signatures:")
        );

        const list = el("div", "pdf-signature-list");
        judges.forEach((judge, index) => {
            const block = el("div", "pdf-signature");
            block.appendChild(
                el(
                    "div",
                    "pdf-signature-name",
                    judge.name.replaceAll("_", " ")
                )
            );
            block.appendChild(
                el("div", "pdf-signature-role", `Judge ${index + 1}`)
            );
            list.appendChild(block);
        });

        signatures.appendChild(list);
        report.appendChild(signatures);
    }

    return report;
};

const PrintButton = ({ title, tableRef, category, judges = [] }) => {
    const [printing, setPrinting] = useState(false);

    const handlePrintPDF = async () => {
        const table = tableRef.current?.querySelector("table");
        if (!table || printing) return;

        const reportTitle = category || title;
        setPrinting(true);

        try {
            // Loaded on demand: the PDF library is ~950 KB and most visits never print.
            const { default: html2pdf } = await import("html2pdf.js");

            await html2pdf()
                .set({
                    margin: 10,
                    filename: `${reportTitle.replace(/\s+/g, "_")}.pdf`,
                    image: { type: "jpeg", quality: 0.98 },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        backgroundColor: "#ffffff",
                    },
                    jsPDF: {
                        unit: "mm",
                        format: "a4",
                        orientation: "landscape",
                    },
                    // Never split a table row or the signature block across pages.
                    pagebreak: { mode: ["css", "legacy"], avoid: [".pdf-signatures", "tr"] },
                })
                .from(buildReport(table, reportTitle, judges))
                .save();
        } finally {
            setPrinting(false);
        }
    };

    return (
        <HoverBorderGradient
            containerClassName="rounded-full"
            as="button"
            className="dark:bg-neutral-800 bg-white text-black dark:text-neutral-100 flex items-center space-x-2 px-12 py-1 text-lg font-semibold"
            onClick={handlePrintPDF}
            disabled={printing}
        >
            <span>{printing ? "Preparing PDF..." : "Print PDF"}</span>
        </HoverBorderGradient>
    );
};

export default PrintButton;
