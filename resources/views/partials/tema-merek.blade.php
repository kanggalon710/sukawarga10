{{--
    Blok warna tema: HANYA custom property dari MerekAplikasi::TOKEN_WARNA
    dengan nilai hex hasil hitungan server. Tidak ada CSS dari pengguna.
    Tidak dirender sama sekali bila warna bawaan dipakai.
--}}
@php $paletMerek = \App\Services\MerekAplikasi::paletKustom(); @endphp
@if($paletMerek !== [])
<style id="tema-merek">:root{@foreach($paletMerek as $tokenMerek => $nilaiMerek){{ $tokenMerek }}:{{ $nilaiMerek }};@endforeach}</style>
@endif
