@extends('layouts.app')
@section('title', '403 · Akses Ditolak')
@section('page-title', 'Akses Ditolak')
@section('page-subtitle', 'Anda tidak memiliki izin untuk mengakses halaman ini')

@section('content')
<div class="card" style="text-align:center; padding:60px 30px;">
    <div style="width:80px; height:80px; border-radius:50%; background:var(--merah-pale); display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
        <i class="fas fa-lock" style="font-size:36px; color:var(--merah);"></i>
    </div>
    <h2 style="color:var(--merah); margin-bottom:8px;">403 · Akses Ditolak</h2>
    <p style="color:var(--text3); font-size:14px; max-width:400px; margin:0 auto 20px;">Anda tidak memiliki hak akses untuk halaman ini. Silakan hubungi administrator jika Anda merasa ini adalah kesalahan.</p>
    <a href="{{ route('dashboard') }}" class="btn btn-primary"><i class="fas fa-home"></i> Kembali ke Dashboard</a>
</div>
@endsection
