Pengajuan merchant ditolak

Halo,

Mohon maaf, pengajuan merchant Anda belum dapat disetujui.
Nomor pengajuan: {{ $application_number }}
Nama usaha: {{ $business_name ?? '-' }}
Alasan: {{ $reason ?? '-' }}

@if (! empty($action_url))
Buka Aplikasi Merchant: {{ $action_url }}
@endif
