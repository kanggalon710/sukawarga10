@extends('layouts.app')
@section('title', 'Akun Saya')
@section('page-title', 'Akun Saya')
@section('page-subtitle', 'Ganti username & PIN Anda sendiri')

@push('styles')
<style>
/* Gaya ber-scope halaman (pola yang sama dengan Manajemen Desa): target
   sentuh 44px + font 16px, karena .sak-input global masih polos. */
.saya-form .sak-label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13.5px; }
.saya-form .sak-input {
    display: block; width: 100%; max-width: 420px; min-height: 44px;
    font-size: 16px; padding: 10px 12px; border: 1px solid var(--abu2, #d5d9d4);
    border-radius: var(--radius, 8px); background: var(--surface, #fff);
}
.saya-form .btn { min-height: 44px; }
</style>
@endpush

@section('content')

@if(session('success'))
<div class="card" style="margin-bottom:16px; border-left:4px solid var(--hijau); padding:12px 16px;" role="status">
    <i class="fas fa-check-circle" style="color:var(--hijau);" aria-hidden="true"></i> {{ session('success') }}
</div>
@endif

<div class="card" style="max-width:560px;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-user-lock" style="color:var(--hijau);" aria-hidden="true"></i> Kredensial login</div>
    </div>
    <form method="POST" action="{{ route('akunSaya.simpan') }}" class="saya-form">
        @csrf
        <div style="margin-bottom:12px;">
            <label class="sak-label" for="saya-username">Username *</label>
            <input type="text" id="saya-username" name="username" required maxlength="60" class="sak-input"
                   value="{{ old('username', auth()->user()->username) }}"
                   @error('username') aria-invalid="true" aria-describedby="err-su" @enderror>
            @error('username')<p id="err-su" role="alert" style="color:var(--merah); font-size:12px; margin:4px 0 0;">{{ $message }}</p>@enderror
        </div>
        <div style="margin-bottom:12px;">
            <label class="sak-label" for="saya-pin-lama">PIN saat ini * <span style="font-weight:400; color:var(--text3);">(untuk memastikan ini benar Anda)</span></label>
            <input type="password" id="saya-pin-lama" name="pin_lama" required pattern="\d{6}" maxlength="6"
                   inputmode="numeric" autocomplete="current-password" class="sak-input" placeholder="6 digit"
                   @error('pin_lama') aria-invalid="true" aria-describedby="err-spl" @enderror>
            @error('pin_lama')<p id="err-spl" role="alert" style="color:var(--merah); font-size:12px; margin:4px 0 0;">{{ $message }}</p>@enderror
        </div>
        <div style="margin-bottom:16px;">
            <label class="sak-label" for="saya-pin-baru">PIN baru <span style="font-weight:400; color:var(--text3);">(kosongkan bila tidak diganti)</span></label>
            <input type="password" id="saya-pin-baru" name="pin_baru" pattern="\d{6}" maxlength="6"
                   inputmode="numeric" autocomplete="new-password" class="sak-input" placeholder="6 digit"
                   @error('pin_baru') aria-invalid="true" aria-describedby="err-spb" @enderror>
            @error('pin_baru')<p id="err-spb" role="alert" style="color:var(--merah); font-size:12px; margin:4px 0 0;">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Simpan</button>
    </form>
</div>

@endsection
