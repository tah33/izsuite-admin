        :root {
            --bg: #0f1117;
            --surface: #1a1d27;
            --surface-hover: #22263a;
            --border: #2a2e3e;
            --text: #e4e6ef;
            --text-muted: #8b8fa3;
            --accent: #2563EB;
            --accent-glow: rgba(37, 99, 235, 0.25);
            --google: #4285F4;
            --linkedin: #0a66c2;
            --success: #00b894;
            --error: #e17055;
            --radius: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 520px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 36px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }

        .logo {
            text-align: center;
            margin-bottom: 8px;
        }

        .logo h1 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), #a29bfe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 32px;
        }

        .config-warning {
            background: rgba(225, 112, 85, 0.1);
            border: 1px solid rgba(225, 112, 85, 0.3);
            border-radius: var(--radius);
            padding: 14px 16px;
            font-size: 13px;
            color: var(--error);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .config-warning code {
            background: rgba(225, 112, 85, 0.15);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* Social Buttons */
        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 14px 20px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--surface);
            color: var(--text);
            font-family: inherit;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 12px;
        }

        .social-btn:hover {
            background: var(--surface-hover);
            border-color: #3a3e50;
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        }

        .social-btn:active { transform: translateY(0); }

        .social-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none;
        }

        .social-btn svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .social-btn.google:hover {
            border-color: var(--google);
            box-shadow: 0 4px 16px rgba(66, 133, 244, 0.15);
        }

        .social-btn.linkedin:hover {
            border-color: var(--linkedin);
            box-shadow: 0 4px 16px rgba(10, 102, 194, 0.15);
        }
