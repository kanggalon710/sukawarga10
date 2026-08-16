@extends('layouts.app')
@section('title', 'Pembaruan Sistem')
@section('page-title', 'Pembaruan Sistem')
@section('page-subtitle', 'Versi aplikasi & update satu klik · khusus admin platform')

@push('styles')
<style>
.pembaruan-aksi { display: flex; flex-wrap: wrap; gap: 10px; }
.pembaruan-aksi .btn { min-height: 44px; }
.pembaruan-log {
    background: var(--code-bg, #f2f4f1); border-radius: var(--radius, 8px);
    padding: 12px 14px; font-family: ui-monospace, Menlo, Consolas, monospace;
    font-size: 13px; overflow-x: auto; white-space: pre-wrap; word-break: break-word;
}
</style>
@endpush

@section('content')

@if(session('success'))
<div class="card" style="margin-bottom:16px; border-left:4px solid var(--hijau); padding:12px 16px;" role="status">
    <i class="fas fa-check-circle" style="color:var(--hijau);" aria-hidden="true"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="card" style="margin-bottom:16px; border-left:4px solid var(--merah); padding:12px 16px;" role="alert">
    <i class="fas fa-triangle-exclamation" style="color:var(--merah);" aria-hidden="true"></i> {{ session('error') }}
</div>
@endif

<div class="card" style="margin-bottom:16px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-code-branch" style="color:var(--hijau);" aria-hidden="true"></i> Versi terpasang</div>
    </div>
    <dl style="margin:0; display:grid; grid-template-columns:auto 1fr; gap:6px 16px; font-size:14px;">
        <dt style="font-weight:700;">Versi</dt><dd style="margin:0;"><code>{{ $versi['hash'] ?: '-' }}</code></dd>
        <dt style="font-weight:700;">Tanggal</dt><dd style="margin:0;">{{ $versi['tanggal'] ?: '-' }}</dd>
        <dt style="font-weight:700;">Rilis</dt><dd style="margin:0;">{{ $versi['judul'] ?: '-' }}</dd>
    </dl>
</div>

<div class="card" style="margin-bottom:16px; @if($status['tersedia']) border-left:4px solid var(--emas); @endif">
    <div class="card-header">
        <div class="card-title">
            <i class="fas {{ $status['tersedia'] ? 'fa-bell' : 'fa-circle-check' }}" style="color:{{ $status['tersedia'] ? 'var(--emas)' : 'var(--hijau)' }};" aria-hidden="true"></i>
            {{ $status['tersedia'] ? 'Pembaruan tersedia ('.$status['jumlah'].' perubahan)' : 'Status pembaruan' }}
        </div>
    </div>

    @if($status['tersedia'])
        <ul style="margin:0 0 14px; padding-left:20px; font-size:13.5px;">
            @foreach($status['daftar'] as $baris)
                <li><code style="font-size:12px;">{{ $baris }}</code></li>
            @endforeach
        </ul>
    @else
        <p style="margin:0 0 14px; font-size:13.5px; color:var(--text3);">
            @if($status['dicek_pada'])
                Aplikasi sudah mutakhir saat terakhir dicek ({{ $status['dicek_pada'] }}).
            @else
                Belum pernah dicek. Klik "Periksa Pembaruan" untuk menghubungi repositori.
            @endif
        </p>
    @endif

    <div class="pembaruan-aksi">
        <form method="POST" action="{{ route('pembaruan.cek') }}">
            @csrf
            <button type="submit" class="btn btn-outline"><i class="fas fa-rotate" aria-hidden="true"></i> Periksa Pembaruan</button>
        </form>
        @if($status['tersedia'])
        <form method="POST" action="{{ route('pembaruan.jalankan') }}"
              onsubmit="return confirm('Jalankan pembaruan sekarang? Aplikasi menarik kode terbaru, menjalankan migrasi database, dan membangun ulang cache. Pastikan backup database masih segar.')">
            @csrf
            <button type="submit" class="btn btn-primary"><i class="fas fa-download" aria-hidden="true"></i> Perbarui Sekarang</button>
        </form>
        @endif
    </div>
    <p style="margin:12px 0 0; font-size:12.5px; color:var(--text3);">
        Update menarik branch <code>production</code> (fast-forward saja), menjalankan
        <code>composer install</code> bila dependensi berubah, <code>migrate --force</code>,
        lalu membangun ulang cache. Kalau gagal, tidak ada langkah lanjutan yang
        dijalankan - selesaikan lewat terminal sesuai <code>DEPLOY.md</code>.
    </p>
</div>

@if(session('logPembaruan'))
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-terminal" aria-hidden="true"></i> Log pembaruan</div></div>
    @foreach(session('logPembaruan') as $langkah)
        <p style="margin:10px 0 4px; font-size:13px; font-weight:700;"><code>$ {{ $langkah['perintah'] }}</code></p>
        <div class="pembaruan-log">{{ $langkah['keluaran'] !== '' ? $langkah['keluaran'] : '(tanpa keluaran)' }}</div>
    @endforeach
</div>
@endif

@endsection
