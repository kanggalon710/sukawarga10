{{--
    Ikon, manifest, dan warna tema dari Pengaturan > Tampilan (MerekAplikasi).
    Satu partial untuk semua <head>: layout aplikasi, login, beranda desa &
    platform. Sertakan SETELAH stylesheet halaman supaya blok tema menang.
--}}
@unless(\App\Services\MerekAplikasi::adaIkonKustom())
    <link rel="icon" type="image/svg+xml" href="{{ ikonAplikasi() }}">
@endunless
<link rel="icon" type="image/png" sizes="32x32" href="{{ ikonAplikasi(32) }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ ikonAplikasi(16) }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ ikonAplikasi(180) }}">
<link rel="manifest" href="{{ route('manifest') }}">
<meta name="theme-color" content="{{ warnaMerek() }}">
@include('partials.tema-merek')
