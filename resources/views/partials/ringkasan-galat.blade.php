{{--
    Ringkasan galat validasi di puncak form bertahap (tambah/ubah KK).
    Form bertahap menyembunyikan langkah lain, jadi galat di langkah 5
    (mis. berkas ditolak) tidak akan terlihat tanpa ringkasan ini.
--}}
@if($errors->any())
    <div class="ringkasan-galat" role="alert" tabindex="-1" id="ringkasanGalat">
        <strong><i class="fas fa-exclamation-circle" aria-hidden="true"></i> Data belum tersimpan.</strong>
        <ul>
            @foreach($errors->all() as $pesan)
                <li>{{ $pesan }}</li>
            @endforeach
        </ul>
        @isset($catatan)<p>{{ $catatan }}</p>@endisset
    </div>
@endif
