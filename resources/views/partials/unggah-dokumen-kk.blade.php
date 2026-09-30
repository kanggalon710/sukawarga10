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
                @php $pdfLama = str_ends_with(strtolower($pathLama), '.pdf'); @endphp
                <div class="unggah-dok__pratinjau">
                    @if($pdfLama)
                        {{-- PDF disajikan sebagai unduhan (lihat DokumenWargaController). --}}
                        <a href="{{ $urlDokumen($kolom) }}" class="btn btn-outline btn-sm" download>
                            <i class="fas fa-download" aria-hidden="true"></i> Unduh {{ $label }} (PDF)
                        </a>
                    @else
                        <img src="{{ $urlDokumen($kolom) }}" alt="{{ $label }} yang tersimpan" width="200" height="150" loading="lazy">
                        <a href="{{ $urlDokumen($kolom) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                            <i class="fas fa-external-link-alt" aria-hidden="true"></i> Lihat {{ $label }}
                        </a>
                    @endif
                </div>
            @endif
            <input type="file" id="berkas-{{ $kolom }}" name="{{ $kolom }}" accept="{{ $accept }}"
                   data-maks-gambar-mb="8" data-maks-pdf-mb="5"
                   class="f-input unggah-dok__input"
                   aria-describedby="berkas-{{ $kolom }}-petunjuk @error($kolom) berkas-{{ $kolom }}-galat @enderror"
                   @error($kolom) aria-invalid="true" @enderror>
            <span class="unggah-dok__hint" id="berkas-{{ $kolom }}-petunjuk">
                {{ $petunjuk }}@if($pathLama) Berkas baru akan menggantikan yang lama.@endif
            </span>
            @error($kolom)
                <div class="unggah-dok__galat" id="berkas-{{ $kolom }}-galat" role="alert">{{ $message }}</div>
            @enderror
            <div class="unggah-dok__galat" id="berkas-{{ $kolom }}-klien" role="alert" hidden></div>
        </div>
    @endforeach
</div>

@once
<script>
// Pemeriksaan awal di browser supaya berkas yang jelas ditolak tidak
// menghapus seluruh isian form saat dikirim. Server (PenyimpanBerkas) tetap
// penentu; ini hanya membaca jenis & ukuran yang dilaporkan browser.
document.querySelectorAll('.unggah-dok__input').forEach(function (input) {
    input.addEventListener('change', function () {
        var galat = document.getElementById(input.id + '-klien');
        var berkas = input.files && input.files[0];
        var pesan = '';
        if (berkas) {
            var diizinkan = input.accept.split(',');
            var pdf = berkas.type === 'application/pdf';
            var maksMb = Number(pdf ? input.dataset.maksPdfMb : input.dataset.maksGambarMb);
            if (berkas.type && diizinkan.indexOf(berkas.type) === -1) {
                pesan = 'Jenis berkas tidak diterima. Pilih berkas sesuai petunjuk di atas.';
            } else if (berkas.size > maksMb * 1048576) {
                pesan = 'Ukuran ' + (pdf ? 'PDF' : 'gambar') + ' maksimal ' + maksMb + ' MB.';
            }
        }
        galat.textContent = pesan;
        galat.hidden = pesan === '';
        if (pesan) {
            input.value = '';
            input.setAttribute('aria-invalid', 'true');
        } else {
            input.removeAttribute('aria-invalid');
        }
    });
});
</script>
@endonce
