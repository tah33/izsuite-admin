<!DOCTYPE html>

<html class="light" lang="en"><head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "tertiary-container": "#e0f2fe",
                        "on-surface": "#0f172a",
                        "on-background": "#0f172a",
                        "on-surface-variant": "#475569",
                        "on-primary-container": "#1e40af",
                        "error": "#ef4444",
                        "on-secondary-container": "#1e293b",
                        "on-tertiary-fixed": "#082f49",
                        "tertiary-fixed": "#e0f2fe",
                        "surface-container-low": "#f1f3fd",
                        "on-secondary": "#ffffff",
                        "background": "#f6f7f8",
                        "on-primary": "#ffffff",
                        "surface-variant": "#f1f5f9",
                        "surface-container": "#ebedf7",
                        "surface": "#f6f7f8",
                        "inverse-on-surface": "#f1f5f9",
                        "secondary": "#475569",
                        "on-tertiary-fixed-variant": "#075985",
                        "on-primary-fixed": "#1e3a8a",
                        "on-secondary-fixed-variant": "#334155",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-highest": "#e0e2ec",
                        "surface-bright": "#ffffff",
                        "surface-dim": "#d7dae3",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "outline-variant": "#e2e8f0",
                        "primary": "#2563EB",
                        "on-secondary-fixed": "#0f172a",
                        "inverse-surface": "#1e293b",
                        "error-container": "#fee2e2",
                        "outline": "#cbd5e1",
                        "on-error": "#ffffff",
                        "tertiary": "#0ea5e9",
                        "on-tertiary-container": "#0369a1",
                        "surface-container-high": "#e6e8f1",
                        "primary-fixed": "#dbeafe",
                        "secondary-container": "#f1f5f9",
                        "on-tertiary": "#ffffff",
                        "inverse-primary": "#a8c8ff",
                        "secondary-fixed-dim": "#e2e8f0",
                        "primary-container": "#dbeafe",
                        "surface-tint": "#2563EB",
                        "secondary-fixed": "#f1f5f9",
                        "primary-fixed-dim": "#bfdbfe",
                        "tertiary-fixed-dim": "#bae6fd",
                        "on-error-container": "#991b1b"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "fontFamily": {
                        "headline": ["Manrope"],
                        "display": ["Manrope"],
                        "body": ["Manrope"],
                        "label": ["Manrope"]
                    }
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        @media print {
            .no-print { display: none !important; }
            .print-shadow-none { shadow: none !important; box-shadow: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-surface font-body text-on-surface antialiased min-h-screen">

<main class="pt-32 pb-20 px-4 flex flex-col items-center">
    <!-- Invoice Document Canvas -->
    <div class="w-full max-w-4xl bg-surface-bright p-8 md:p-16 rounded-xl shadow-2xl print-shadow-none relative overflow-hidden border border-outline-variant/30">
        <!-- Azure Logic Signature Accent -->
        <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full -mr-32 -mt-32 blur-3xl"></div>
        <!-- Invoice Header -->
        <div class="flex flex-col md:flex-row justify-between items-start gap-12 mb-16 relative z-10">
            <div class="flex flex-col gap-6">
                <div class="flex items-center gap-2">
                    <img src="{{ asset(config('brand.assets.wordmark')) }}"
                         alt="{{ setting('site_name', config('brand.name')) }}"
                         class="h-9 w-auto">
                </div>
                <div class="text-on-surface-variant leading-relaxed">
                    <p class="font-bold text-on-surface">Precision Engineered Logic Inc.</p>
                    <p>128 Tech Plaza, Suite 400</p>
                    <p>Palo Alto, CA 94301</p>
                    <p>contact@resumeboost.ai</p>
                </div>
            </div>
            <div class="text-left md:text-right flex flex-col gap-2">
                <h2 class="text-5xl font-black text-primary tracking-tighter uppercase">RECEIPT</h2>
                <div class="space-y-1">
                    <p class="text-sm font-bold uppercase tracking-widest text-on-surface-variant">Invoice Number</p>
                    <p class="text-xl font-bold">#RB-AI-2024-8842</p>
                </div>
            </div>
        </div>
        <!-- Billing Info Bento -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
            <div class="p-6 bg-surface-container-low rounded-xl border border-outline-variant/20">
                <h3 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-4">Billed To</h3>
                <div class="text-on-surface font-medium space-y-1">
                    <p class="text-lg font-bold">Jonathan Sterling</p>
                    <p>Senior Product Designer</p>
                    <p>jonathan.sterling@example.com</p>
                    <p>San Francisco, CA</p>
                </div>
            </div>
            <div class="p-6 bg-surface-container-low rounded-xl border border-outline-variant/20">
                <h3 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-4">Payment Details</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-bold text-on-surface-variant uppercase">Date Issued</p>
                        <p class="font-bold">Oct 12, 2024</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-on-surface-variant uppercase">Billing Period</p>
                        <p class="font-bold">Monthly</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-on-surface-variant uppercase">Payment Method</p>
                        <p class="font-bold flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">credit_card</span>
                            Card ending 4421
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-on-surface-variant uppercase">Status</p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-black uppercase bg-green-50 text-green-800">Paid</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Items Table -->
        <div class="mb-12 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                <tr class="border-b-2 border-on-surface">
                    <th class="py-4 text-sm font-bold uppercase tracking-widest text-on-surface-variant">Description</th>
                    <th class="py-4 px-4 text-sm font-bold uppercase tracking-widest text-on-surface-variant text-center">Qty</th>
                    <th class="py-4 text-sm font-bold uppercase tracking-widest text-on-surface-variant text-right">Price</th>
                    <th class="py-4 text-sm font-bold uppercase tracking-widest text-on-surface-variant text-right">Amount</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/30">
                <tr>
                    <td class="py-6">
                        <p class="font-bold text-lg">Admin Plan Purchase</p>
                        <p class="text-sm text-on-surface-variant">Full access to AI analysis, custom templates, and direct PDF exports. (Oct 12 - Nov 12)</p>
                    </td>
                    <td class="py-6 px-4 text-center font-bold">1</td>
                    <td class="py-6 text-right font-bold">$24.00</td>
                    <td class="py-6 text-right font-bold">$24.00</td>
                </tr>
                </tbody>
            </table>
        </div>
        <!-- Totals Logic -->
        <div class="flex justify-end mb-16">
            <div class="w-full md:w-80 space-y-4">
                <div class="flex justify-between items-center text-on-surface-variant">
                    <span class="font-medium">Subtotal</span>
                    <span class="font-bold">$24.00</span>
                </div>
                <div class="flex justify-between items-center text-on-surface-variant">
                    <span class="font-medium">Tax (0%)</span>
                    <span class="font-bold">$0.00</span>
                </div>
                <div class="pt-4 border-t-2 border-outline-variant flex justify-between items-center">
                    <span class="text-xl font-black uppercase tracking-tight">Total Paid</span>
                    <span class="text-3xl font-black text-primary">$24.00</span>
                </div>
            </div>
        </div>
        <!-- AI Feedback / Notes -->
        <div class="bg-green-50 p-6 rounded-xl border border-green-100 flex gap-4 items-start mb-8">
            <span class="material-symbols-outlined text-green-700" style="font-variation-settings: 'FILL' 1;">check_circle</span>
            <div>
                <p class="text-green-800 font-bold text-sm uppercase tracking-wide mb-1">Payment Successful</p>
                <p class="text-green-700 leading-relaxed text-sm">Thank you for your business. Your Admin features are now active. This invoice serves as an official receipt of payment for your records.</p>
            </div>
        </div>
        <!-- Footer Graphic -->
        <div class="mt-16 pt-8 border-t border-outline-variant/30 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="text-sm text-on-surface-variant font-medium text-center md:text-left">
                Precision Engineered Logic. Generated via ResumeBoost AI Infrastructure.
            </div>
            <div class="flex gap-4">
                <div class="w-12 h-1 bg-primary rounded-full"></div>
                <div class="w-8 h-1 bg-primary/40 rounded-full"></div>
                <div class="w-4 h-1 bg-primary/20 rounded-full"></div>
            </div>
        </div>
    </div>
</main>
</body></html>
