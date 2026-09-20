Perbaikan pengajuan merchant diperlukan

Halo,

Pengajuan merchant Anda memerlukan perbaikan data.
Nomor pengajuan: {{ $application_number }}
Nama usaha: {{ $business_name ?? '-' }}
@if (! empty($note))
Catatan: {{ $note }}
@endif

Silakan perbaiki data Anda dan ajukan kembali.

@if (! empty($action_url))
Buka Aplikasi Merchant: {{ $action_url }}
@endif
