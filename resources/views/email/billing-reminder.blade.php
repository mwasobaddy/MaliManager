<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Billing reminder for {{ $organizationName }}</title>
</head>
<body style="margin:0;padding:24px;background:#F5F2EB;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#2A2621;">
    <div style="max-width:480px;margin:0 auto;background:#FFFFFF;border:1px solid #E7E1D6;border-radius:10px;overflow:hidden;">
        <div style="background:#2E2622;padding:20px 32px;">
            <span style="font-size:20px;font-weight:600;"><span style="color:#E2956C;">Mali</span><span style="color:#EAE6DF;">Manager</span></span>
        </div>
        <div style="padding:32px;">
            <h1 style="margin-top:0;font-size:20px;color:#2A2621;">Billing reminder</h1>
            <p style="color:#2A2621;">{{ $body }}</p>
            <p style="color:#2A2621;">Plan: {{ $planName }}</p>
            <p style="color:#6B635A;font-size:13px;">Sign in to MaliManager to pay from the Billing page. To change how you receive these reminders, update your notification preference in settings.</p>
        </div>
    </div>
</body>
</html>
