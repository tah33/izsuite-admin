        .totals-wrap {
            width: 100%;
            margin-bottom: 24px;
            text-align: right;
        }

        .totals-box {
            display: inline-block;
            width: 270px;
            text-align: left;
        }

        .totals-table td {
            padding: 6px 0;
            color: #64748b;
            font-weight: 700;
        }

        .totals-table td:last-child {
            text-align: right;
            color: #0f172a;
            font-weight: 900;
        }

        .total-row td {
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
            color: #0f172a;
            font-size: 18px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .total-row td:last-child {
            color: {{ config('brand.colors.primary') }};
            font-size: 27px;
        }

        .success-box {
            width: 100%;
            margin: 24px 0 24px;
            padding: 18px 24px;
            background: #f0fdf4;
            border: 1px solid #dcfce7;
            border-radius: 14px;
        }

        .success-icon {
            width: 38px;
            vertical-align: top;
            padding-top: 2px;
        }

        .success-icon img {
            display: block;
            width: 18px;
            height: 18px;
        }

        .success-title {
            margin: 0 0 6px;
            color: #166534;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .success-copy {
            margin: 0;
            color: #15803d;
            font-size: 13px;
            line-height: 1.55;
        }

        .footer-table {
            border-top: 1px solid #e2e8f0;
        }

        .footer-copy {
            padding-top: 16px;
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
        }

        .bar {
            display: inline-block;
            height: 5px;
            margin-left: 9px;
            border-radius: 999px;
            background: {{ config('brand.colors.primary') }};
            vertical-align: middle;
        }

        .bar.one {
            width: 46px;
        }

        .bar.two {
            width: 30px;
            opacity: 0.45;
        }

        .bar.three {
            width: 16px;
            opacity: 0.24;
        }
