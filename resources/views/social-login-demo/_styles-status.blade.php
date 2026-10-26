        /* Spinner */
        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Status area */
        .status {
            margin-top: 20px;
            display: none;
        }

        .status.show { display: block; }

        .status-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-header.success { color: var(--success); }
        .status-header.error { color: var(--error); }
        .status-header.loading { color: var(--accent); }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .status-dot.success { background: var(--success); box-shadow: 0 0 8px var(--success); }
        .status-dot.error { background: var(--error); box-shadow: 0 0 8px var(--error); }
        .status-dot.loading { background: var(--accent); box-shadow: 0 0 8px var(--accent); animation: pulse 1s infinite; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .token-box {
            background: #12141c;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .token-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .token-value {
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 12px;
            color: var(--text);
            word-break: break-all;
            line-height: 1.6;
            max-height: 90px;
            overflow-y: auto;
        }

        .copy-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-muted);
            font-family: inherit;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .copy-btn:hover {
            border-color: var(--accent);
            color: var(--text);
        }

        .copy-btn.copied {
            border-color: var(--success);
            color: var(--success);
        }

        /* API Response box */
        .response-box {
            background: #12141c;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-top: 12px;
            overflow: hidden;
        }

        .response-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: rgba(255,255,255,0.02);
            border-bottom: 1px solid var(--border);
        }

        .response-header span {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .http-status {
            font-size: 12px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .http-status.s2xx { background: rgba(0,184,148,0.15); color: var(--success); }
        .http-status.s4xx { background: rgba(225,112,85,0.15); color: var(--error); }
        .http-status.s5xx { background: rgba(214,48,49,0.15); color: #d63031; }

        .response-body {
            padding: 14px;
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 12px;
            line-height: 1.6;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            color: var(--text);
        }

        .footer-note {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .footer-note a {
            color: var(--accent);
            text-decoration: none;
        }
