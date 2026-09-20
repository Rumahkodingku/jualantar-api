<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan merchant disetujui</title>
</head>
<body style="margin:0;padding:24px;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;padding:32px;">
        <h1 style="font-size:20px;margin:0 0 16px;">Selamat!</h1>
        <p style="font-size:14px;line-height:1.6;margin:0 0 12px;">Pengajuan merchant Anda telah disetujui.</p>
        <p style="font-size:14px;line-height:1.6;margin:0 0 4px;">Nomor pengajuan: {{ $application_number }}</p>
        <p style="font-size:14px;line-height:1.6;margin:0 0 24px;">Nama usaha: {{ $business_name ?? '-' }}</p>
        <p style="font-size:14px;line-height:1.6;margin:0 0 24px;">Akun merchant Anda kini aktif.</p>
        @if (! empty($action_url))
            <p style="margin:0;">
                <a href="{{ $action_url }}"
                   style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:6px;font-size:14px;">
                    Buka Aplikasi Merchant
                </a>
            </p>
        @endif
    </div>
</body>
</html>
