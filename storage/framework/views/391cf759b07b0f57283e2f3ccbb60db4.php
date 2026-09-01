<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f7; padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding:32px 40px; text-align:center;">
                            <h1 style="margin:0; color:#ffffff; font-size:24px; font-weight:700;"><?php echo e($siteName); ?></h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px;">
                            <h2 style="margin:0 0 16px; color:#1a1a2e; font-size:20px; font-weight:600;">Your mail settings are working</h2>
                            <p style="margin:0 0 24px; color:#51545e; font-size:15px; line-height:1.6;">
                                This is a test message sent from the admin panel. If you are reading it, the SMTP credentials saved under <strong>Settings &rarr; Mail</strong> are able to deliver email.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f7; border-radius:8px; padding:20px; margin:0 0 24px;">
                                <tr>
                                    <td style="padding:6px 0; color:#8a8a99; font-size:13px;">Host</td>
                                    <td style="padding:6px 0; color:#1a1a2e; font-size:13px; text-align:right; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;"><?php echo e($host ?: 'not set'); ?></td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0; color:#8a8a99; font-size:13px;">Port</td>
                                    <td style="padding:6px 0; color:#1a1a2e; font-size:13px; text-align:right; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;"><?php echo e($port ?: 'not set'); ?></td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0; color:#8a8a99; font-size:13px;">Sent at</td>
                                    <td style="padding:6px 0; color:#1a1a2e; font-size:13px; text-align:right; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;"><?php echo e($sentAt); ?></td>
                                </tr>
                            </table>

                            <p style="margin:0; color:#8a8a99; font-size:13px; line-height:1.6;">
                                No action is needed. You can safely delete this message.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#fafafa; padding:24px 40px; text-align:center; border-top:1px solid #eaeaec;">
                            <p style="margin:0; color:#8a8a99; font-size:12px;">&copy; <?php echo e(date('Y')); ?> <?php echo e($siteName); ?></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
<?php /**PATH C:\laragon\www\izsuite-admin\resources\views/emails/test-mail.blade.php ENDPATH**/ ?>