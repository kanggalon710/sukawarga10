@extends('layouts.app')
@section('title', 'Matriks Kapabilitas')
@section('page-title', 'Matriks Kapabilitas')
@section('page-subtitle', $rw->name . ' · khusus admin platform')

@push('styles')
<style>
/* Ber-scope halaman: tabel matriks lebar, jadi ia yang scroll sendiri -
   body halaman tidak boleh ikut scroll horizontal. */
.matriks-bungkus { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.matriks-tabel { width: 100%; min-width: 620px; border-collapse: collapse; font-size: 13.5px; }
.matriks-tabel th, .matriks-tabel td { padding: 8px 10px; border-bottom: 1px solid var(--abu2, #e3e6e2); text-align: left; }
.matriks-tabel thead th { position: sticky; top: 0; background: var(--surface, #fff); z-index: 1; font-size: 12px; }
.matriks-tabel td.pilih, .matriks-tabel th.pilih { text-align: center; width: 92px; }
.matriks-modul { background: var(--abu, #f4f6f3); font-weight: 700; font-size: 12px; letter-spacing: .04em; text-transform: uppercase; }
.matriks-key { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11.5px; color: var(--text3, #6b7280); display: block; }
/* Target sentuh 44px ditegakkan lewat tinggi label, bukan margin. */
.matriks-tabel label { display: flex; align-items: center; justify-content: center; min-height: 44px; cursor: pointer; }
.matriks-tabel input[type="checkbox"] { width: 20px; height: 20px; }
.matriks-aksi { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
.matriks-aksi .btn { min-height: 44px; }
</style>
@endpush

@section('content')

@php
    $peranLabel = [
        'ketua_rw' => 'Ketua RW',
        'sekretaris' => 'Sekretaris',
        'bendahara' => 'Bendahara',
        'petugas_rt' => 'Petugas RT',
        'warga' => 'Warga',
    ];
    $katalog = \App\Services\MatriksKapabilitas::KATALOG;
    $terlarang = \App\Services\MatriksKapabilitas::TERLARANG_OVERRIDE;
@endphp

@if(session('success'))
<div class="card" style="border-left:4px solid var(--hijau); margin-bottom:14px;" role="status">
    <i class="fas fa-check-circle" style="color:var(--hijau);" aria-hidden="true"></i> {{ session('success') }}
</div>
@endif

@error('kapabilitas')
<div class="card" style="border-left:4px solid var(--merah); margin-bottom:14px;" role="alert">
    <i class="fas fa-triangle-exclamation" style="color:var(--merah);" aria-hidden="true"></i> {{ $message }}
</div>
@enderror

<section class="card" style="margin-bottom:14px;">
    <h2 style="font-size:15px; margin:0 0 6px;">Pembagian tugas pengurus {{ $rw->name }}</h2>
    <p style="font-size:13px; color:var(--text3); margin:0;">
        Centang berarti peran itu boleh melakukannya. Perubahan hanya berlaku untuk RW ini;
        yang tidak diubah mengikuti bawaan aplikasi, termasuk kemampuan baru pada pembaruan berikutnya.
        Pengurus RW hanya bisa melihat matriks ini, tidak mengubahnya.
        Super Admin selalu memegang semuanya dan sengaja tidak ditampilkan di sini.
    </p>
</section>

<form method="POST" action="{{ route('tenant.rw.matriks.simpan', $rw->id) }}">
    @csrf
    <div class="card" style="padding:0;">
        <div class="matriks-bungkus">
            <table class="matriks-tabel">
                <caption class="visually-hidden">Kapabilitas per peran di {{ $rw->name }}</caption>
                <thead>
                    <tr>
                        <th scope="col">Kemampuan</th>
                        @foreach($peranLabel as $kunci => $label)
                        <th scope="col" class="pilih">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php $modulSebelumnya = null; @endphp
                    @foreach($katalog as $kapabilitas => $meta)
                        @if(in_array($kapabilitas, $terlarang, true)) @continue @endif
                        @if($meta['modul'] !== $modulSebelumnya)
                        <tr class="matriks-modul">
                            <th scope="colgroup" colspan="{{ count($peranLabel) + 1 }}">{{ $meta['modul'] }}</th>
                        </tr>
                        @php $modulSebelumnya = $meta['modul']; @endphp
                        @endif
                        <tr>
                            <th scope="row" style="font-weight:500;">
                                {{ $meta['label'] }}
                                <code class="matriks-key">{{ $kapabilitas }}</code>
                            </th>
                            @foreach($peranLabel as $kunci => $label)
                            <td class="pilih">
                                <label>
                                    <span class="visually-hidden">{{ $label }} boleh {{ $meta['label'] }}</span>
                                    <input type="checkbox"
                                           name="kapabilitas[{{ $kunci }}][{{ $kapabilitas }}]"
                                           value="1"
                                           @checked(in_array($kapabilitas, $matriks[$kunci] ?? [], true))>
                                </label>
                            </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="matriks-aksi">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Simpan Matriks</button>
        <a href="{{ route('tenant.index') }}" class="btn btn-outline">Kembali ke Manajemen Desa</a>
    </div>
</form>

@endsection
