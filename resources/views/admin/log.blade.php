@extends('layouts.app')
@section('title', 'Log Sistem')
@section('page-title', 'Log Sistem')
@section('page-subtitle', 'Audit trail aktivitas pengguna')

@section('content')
<div class="toolbar">
    <div class="toolbar-left"><span style="font-size:13px; font-weight:600;">{{ count($logs) }} log tercatat</span></div>
</div>

@if(count($logs) > 0)
<div class="card" style="padding:0; overflow:hidden;">
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead><tr><th>Waktu</th><th>Aksi</th><th>Koleksi</th><th>Deskripsi</th><th>Operator</th></tr></thead>
            <tbody>
                @foreach($logs as $log)
                <tr>
                    <td style="white-space:nowrap; font-size:12px;">{{ $log->tanggal ? date('d M Y H:i', strtotime($log->tanggal)) : '-' }}</td>
                    <td>
                        @php $aksiColor = ['CREATE'=>'var(--hijau)', 'UPDATE'=>'var(--biru)', 'DELETE'=>'var(--merah)', 'LOGIN'=>'var(--emas)']; @endphp
                        <span class="badge" style="background:{{ ($aksiColor[strtoupper($log->aksi)] ?? 'var(--abu3)') }}15; color:{{ $aksiColor[strtoupper($log->aksi)] ?? 'var(--text2)' }}; padding:4px 10px; border-radius:6px; font-size:11px; text-transform:uppercase;">{{ $log->aksi }}</span>
                    </td>
                    <td style="font-size:12px; color:var(--text3);">{{ $log->collection }}</td>
                    <td style="max-width:300px;">{{ $log->deskripsi ?? '-' }}</td>
                    <td style="font-weight:600; font-size:12px;">{{ $log->operator ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="card" style="text-align:center; padding:40px;">
    <i class="fas fa-history" style="font-size:48px; color:var(--abu3); margin-bottom:16px;"></i>
    <h3 style="color:var(--text2);">Log Masih Kosong</h3>
    <p style="color:var(--text3); font-size:13px;">Aktivitas akan tercatat secara otomatis.</p>
</div>
@endif
@endsection
