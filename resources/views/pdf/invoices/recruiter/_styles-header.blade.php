        @page {
            margin: 20px;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: #0f172a;
            font-family: ManropePdf, DejaVu Sans, sans-serif;
            font-size: 13px;
            line-height: 1.5;
        }

        .page {
            padding: 0;
        }

        .invoice-card {
            overflow: visible;
            background: #ffffff;
            border-radius: 18px;
            padding: 24px 26px 10px;
        }

        .header-table,
        .info-table,
        .totals-table,
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table {
            margin-bottom: 30px;
            table-layout: fixed;
        }

        .header-table td {
            vertical-align: top;
        }

        .brand-row {
            margin-bottom: 22px;
        }

        .brand-mark {
            display: inline-block;
            width: 34px;
            height: 34px;
            vertical-align: middle;
        }

        .brand-mark img {
            display: block;
            width: 34px;
            height: 34px;
        }

        .brand-name {
            display: inline-block;
            margin-left: 9px;
            color: #0f172a;
            font-size: 22px;
            font-weight: 900;
            letter-spacing: -0.6px;
            vertical-align: middle;
        }

        .company {
            color: #475569;
        }

        .company strong {
            display: block;
            color: #0f172a;
            font-weight: 800;
        }

        .receipt-title {
            color: {{ config('brand.colors.primary') }};
            font-size: 30px;
            font-weight: 900;
            letter-spacing: 0;
            line-height: 1;
            margin: 0 0 14px;
            text-align: right;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .eyebrow {
            color: #64748b;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .invoice-number {
            color: #0f172a;
            font-size: 16px;
            font-weight: 800;
            text-align: right;
            letter-spacing: 0;
            word-spacing: normal;
            font-family: DejaVu Sans, sans-serif;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .info-table {
            margin-bottom: 30px;
            table-layout: fixed;
        }

        .info-cell {
            vertical-align: top;
        }

        .info-gap {
            width: 18px;
        }

        .panel {
            height: 178px;
            padding: 20px;
            background: #f1f3fd;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
        }

        .panel-title {
            margin: 0 0 14px;
            color: #64748b;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .bill-name {
            margin: 0 0 3px;
            color: #0f172a;
            font-size: 18px;
            font-weight: 900;
        }

        .muted {
            color: #64748b;
        }
