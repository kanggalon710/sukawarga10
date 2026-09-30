{{--
    Field unggah dokumen KK: satu markup untuk form tambah KK, ubah KK, dan
    Profil Saya. Jenis & batasnya mengikuti PenyimpanBerkas (yang memeriksa
    ulang isi berkas di server; `accept` di sini hanya bantuan pemilih berkas).

    Parameter:
      $kk          ?Keluarga  - null di form tambah
      $urlDokumen  ?Closure   - fn(string $kolom): string, URL penyaji berizin
--}}
@php
    $gambarDanPdf = 'image/jpeg,image/png,image/webp,application/pdf';
    $daftarBerkas = [
        'fotoKK' => ['📋', 'Scan Kartu Keluarga', 'JPG, PNG, WebP (maks 8 MB) atau PDF (maks 5 MB).', $gambarDanPdf],
        'fotoRumah' => ['🏠', 'Foto Rumah', 'JPG, PNG, atau WebP. Maks 8 MB.', 'image/jpeg,image/png,image/webp'],
        'dokumenPBB' => ['📄', 'Dokumen PBB', 'JPG, PNG, WebP (maks 8 MB) atau PDF (maks 5 MB).', $gambarDanPdf],
    ];
@endphp
<div class="unggah-dok">
    @foreach($daftarBerkas as $kolom => [$ikon, $label, $petunjuk, $accept])
        @php $pathLama = $kk?->{$kolom}; @endphp
        <div class="unggah-dok__field">
            <label class="f-label" for="berkas-{{ $kolom }}"><span aria-hidden="true">{{ $ikon }}</span> {{ $label }}</label>
            @if($pathLama && isset($urlDokumen))
                <div class="unggah-dok__pratinjau">
                    @unless(str_ends_with(strtolower($pathLama), '.pdf'))
                        <img src="{{ $urlDokumen($kolom) }}" alt="{{ $label }} yang tersimpan" width="200" height="150" loading="lazy">
                    @endunless
                    <a href="{{ $urlDokumen($kolom) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                        <i class="fas fa-external-link-alt" aria-hidden="true"></i> Lihat {{ $label }}
                    </a>
                </div>
            @endif
            <input type="file" id="berkas-{{ $kolom }}" name="{{ $kolom }}" accept="{{ $accept }}"
                   class="f-input unggah-dok__input"
                   aria-describedby="berkas-{{ $kolom }}-petunjuk @error($kolom) berkas-{{ $kolom }}-galat @enderror"
                   @error($kolom) aria-invalid="true" @enderror>
            <span class="unggah-dok__hint" id="berkas-{{ $kolom }}-petunjuk">
                {{ $petunjuk }}@if($pathLama) Berkas baru akan menggantikan yang lama.@endif
            </span>
            @error($kolom)
                <div class="unggah-dok__galat" id="berkas-{{ $kolom }}-galat" role="alert">{{ $message }}</div>
            @enderror
        </div>
    @endforeach
</div>
