<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Billing reminder for {{ $organizationName }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;">
        <h1 style="margin-top:0;font-size:20px;">Billing reminder</h1>
        <p style="color:#52525b;">{{ $body }}</p>
        <p style="color:#52525b;">Plan: {{ $planName }}</p>
        <p style="color:#a1a1aa;font-size:13px;">Sign in to MaliManager to pay from the Billing page. To change how you receive these reminders, update your notification preference in settings.</p>
    </div>
</body>
</html>
