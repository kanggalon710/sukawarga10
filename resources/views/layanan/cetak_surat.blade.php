@extends('layouts.app')
@section('title', 'Cetak Surat · ' . $surat->nomorSurat)

@section('content')
<style>
@media print {
    .no-print, .sidebar, .top-header, .bottom-nav, .toolbar { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    .page-content { padding: 0 !important; }
    .surat-wrapper { border: none !important; box-shadow: none !important; }
}
.surat-wrapper {
    max-width: 700px; margin: 0 auto; padding: 40px 50px; background: white;
    border: 1px solid var(--abu2); border-radius: 8px; font-family: 'Times New Roman', serif;
    font-size: 14px; line-height: 1.8; color: #222;
}
.kop-rw { display: flex; align-items: center; gap: 16px; text-align: center; border-bottom: 3px double #333; padding-bottom: 12px; margin-bottom: 24px; }
.kop-logo { width: 68px; height: 68px; flex-shrink: 0; }
.kop-teks { flex: 1; }
/* Cetak: pastikan logo ikut tercetak (bukan dianggap latar) */
@media print { .kop-logo { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
.kop-rw h2 { font-size: 18px; margin: 0; text-transform: uppercase; letter-spacing: 1px; }
.kop-rw h3 { font-size: 14px; margin: 2px 0 0; font-weight: normal; }
.kop-rw .alamat { font-size: 11px; color: #555; margin-top: 4px; }
.nomor-surat { text-align: center; margin-bottom: 28px; font-size: 13px; }
.nomor-surat strong { font-size: 15px; text-transform: uppercase; text-decoration: underline; }
.isi-surat p { text-indent: 40px; margin: 8px 0; text-align: justify; }
.isi-surat .data-diri { margin: 16px 0 16px 40px; border-collapse: collapse; }
.isi-surat .data-diri td { padding: 3px 12px 3px 0; vertical-align: top; font-size: 14px; }
.ttd-area { margin-top: 40px; display: flex; justify-content: flex-end; }
.ttd-box { text-align: center; min-width: 220px; }
.ttd-box .nama { font-weight: bold; border-bottom: 1px solid #333; padding-bottom: 2px; display: inline-block; margin-top: 60px; }
.draft-watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg);
    font-size: 120px; color: rgba(200, 0, 0, 0.08); font-weight: 900; pointer-events: none; z-index: 0; }
</style>

<div class="toolbar no-print">
    <div class="toolbar-left">
        <a href="{{ route('surat.index') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>
    <div class="toolbar-right">
        <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
    </div>
</div>

@if($surat->status === 'draft')
<div class="draft-watermark">DRAFT</div>
@endif

@php
    $rw = $settings['nama_rw'] ?? '10';
    $kel = $settings['kelurahan'] ?? 'Kelurahan Sukakarya';
    $kec = $settings['kecamatan'] ?? 'Warudoyong';
    $kab = $settings['kabupaten'] ?? 'Kota Sukabumi';
    $ketua = $settings['ketua_rw'] ?? '___________________';
    $alamatRW = $settings['alamat_rw'] ?? '';

    $judulMap = [
        'SKD'  => 'SURAT KETERANGAN DOMISILI',
        'SKTM' => 'SURAT KETERANGAN TIDAK MAMPU',
        'SKP'  => 'SURAT KETERANGAN PINDAH',
        'SKU'  => 'SURAT KETERANGAN USAHA',
        'SKCK' => 'SURAT PENGANTAR SKCK',
        'SKK'  => 'SURAT KETERANGAN KEMATIAN',
        'SKL'  => 'SURAT KETERANGAN KELAHIRAN',
        'SKN'  => 'SURAT PENGANTAR NIKAH',
        'SKBB' => 'SURAT KETERANGAN BERKELAKUAN BAIK',
        'SKI'  => 'SURAT KETERANGAN IZIN KERAMAIAN',
        'SKKB' => 'SURAT KETERANGAN KEHILANGAN',
        'SPB'  => 'SURAT PENGANTAR BANTUAN',
        'LAIN' => 'SURAT KETERANGAN',
    ];
    $judul = $judulMap[$surat->kodeSurat] ?? 'SURAT KETERANGAN';
@endphp

<div class="surat-wrapper">
    {{-- KOP SURAT --}}
    <div class="kop-rw">
        <img src="{{ asset('logo-sukawarga-icon.svg') }}" alt="Logo RW 10 Sukakarya" class="kop-logo">
        <div class="kop-teks">
            <h2>RUKUN WARGA {{ $rw }}</h2>
            <h3>{{ $kel }}</h3>
            <div class="alamat">
                Kecamatan {{ $kec }} · {{ $kab }}<br>
                {{ $alamatRW }}
            </div>
        </div>
    </div>

    {{-- NOMOR SURAT --}}
    <div class="nomor-surat">
        <strong>{{ $judul }}</strong><br>
        Nomor: {{ $surat->nomorSurat }}
    </div>

    {{-- ISI SURAT --}}
    <div class="isi-surat">

        @switch($surat->kodeSurat)

        @case('SKD')
            <p>Yang bertanda tangan di bawah ini, Ketua RW {{ $rw }} {{ $kel }}, Kecamatan {{ $kec }}, {{ $kab }}, dengan ini menerangkan bahwa:</p>
            <table class="data-diri">
                <tr><td>Nama</td><td>: <strong>{{ $surat->pemohon }}</strong></td></tr>
                <tr><td>Keperluan</td><td>: {{ $surat->keperluan ?: 'Keterangan domisili' }}</td></tr>
            </table>
            <p>Adalah benar warga yang berdomisili di wilayah RW {{ $rw }}, {{ $kel }}, dan surat ini dibuat sebagai keterangan bahwa yang bersangkutan benar-benar bertempat tinggal di alamat tersebut.</p>
            <p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>
        @break

        @case('SKTM')
            <p>Yang bertanda tangan di bawah ini, Ketua RW {{ $rw }} {{ $kel }}, Kecamatan {{ $kec }}, {{ $kab }}, dengan ini menerangkan bahwa:</p>
            <table class="data-diri">
                <tr><td>Nama</td><td>: <strong>{{ $surat->pemohon }}</strong></td></tr>
                <tr><td>Keperluan</td><td>: {{ $surat->keperluan ?: 'Pengajuan bantuan / keringanan biaya' }}</td></tr>
            </table>
            <p>Adalah benar warga RW {{ $rw }} yang termasuk dalam keluarga kurang mampu. Surat ini diberikan guna memenuhi persyaratan administrasi untuk keperluan sebagaimana tersebut di atas.</p>
            <p>Demikian surat keterangan ini dibuat dengan sebenarnya, dan apabila dikemudian hari terdapat keterangan yang tidak benar, maka yang bersangkutan bersedia menanggung segala risiko yang timbul.</p>
        @break

        @case('SKP')
            <p>Yang bertanda tangan di bawah ini, Ketua RW {{ $rw }} {{ $kel }}, Kecamatan {{ $kec }}, {{ $kab }}, dengan ini menerangkan bahwa:</p>
            <table class="data-diri">
                <tr><td>Nama</td><td>: <strong>{{ $surat->pemohon }}</strong></td></tr>
                <tr><td>Keperluan</td><td>: {{ $surat->keperluan ?: 'Pindah domisili' }}</td></tr>
            </table>
            <p>Bermaksud untuk pindah dari wilayah RW {{ $rw }}, {{ $kel }}. Yang bersangkutan telah memenuhi kewajiban administrasi selama bertempat tinggal di wilayah kami, termasuk iuran warga dan kewajiban sosial lainnya.</p>
            <p>Demikian surat keterangan pindah ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>
        @break

        @case('SKU')
            <p>Yang bertanda tangan di bawah ini, Ketua RW {{ $rw }} {{ $kel }}, Kecamatan {{ $kec }}, {{ $kab }}, dengan ini menerangkan bahwa:</p>
            <table class="data-diri">
                <tr><td>Nama</td><td>: <strong>{{ $surat->pemohon }}</strong></td></tr>
                <tr><td>Jenis Usaha</td><td>: {{ $surat->keperluan ?: 'Usaha rumahan / UMKM' }}</td></tr>
            </table>
            <p>Adalah benar warga RW {{ $rw }} yang menjalankan usaha sebagaimana tersebut di atas di wilayah kami. Surat ini diberikan sebagai keterangan bahwa yang bersangkutan benar menjalankan usaha tersebut.</p>
            <p>Demikian surat keterangan usaha ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>
        @break

        @case('SKCK')
            <p>Yang bertanda tangan di bawah ini, Ketua RW {{ $rw }} {{ $kel }}, Kecamatan {{ $kec }}, {{ $kab }}, dengan ini memberikan pengantar bahwa:</p>
            <table class="data-diri">
                <tr><td>Nama</td><td>: <strong>{{ $surat->pemohon }}</strong></td></tr>
                <tr><td>Keperluan</td><td>: {{ $surat->keperluan ?: 'Pengurusan SKCK di Kepolisian' }}</td></tr>
            </table>
            <p>Adalah benar warga RW {{ $rw }} yang bermaksud mengurus Surat Keterangan Catatan Kepolisian (SKCK). Sepanjang pengetahuan kami, yang bersangkutan berkelakuan baik dan tidak pernah terlibat dalam tindakan kriminal.</p>
            <p>Demikian surat pengantar ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>
        @break

        @case('SKK')
            <p>Yang bertanda tangan di bawah ini, Ketua RW {{ $rw }} {{ $kel }}, Kecamatan {{ $kec }}, {{ $kab }}, dengan ini menerangkan bahwa:</p>
            <table class="data-diri">
                <tr><td>Nama (alm/almh)</td><td>: <strong>{{ $surat->pemohon }}</strong></td></tr>
                <tr><td>Keterangan</td><td>: {{ $surat->keperluan ?: 'Telah meninggal dunia' }}</td></tr>
            </table>
            <p>Adalah benar warga RW {{ $rw }} yang telah meninggal dunia. Surat ini diberikan untuk keperluan administrasi kependudukan dan pengurusan hak-hak almarhum/almarhumah.</p>
            <p>Demikian surat keterangan kematian ini dibuat dengan sebenarnya.</p>
        @break

        @case('SKL')
            <p>Yang bertanda tangan di bawah ini, Ketua RW {{ $rw }} {{ $kel }}, Kecamatan {{ $kec }}, {{ $kab }}, dengan ini menerangkan bahwa:</p>
            <table class="data-diri">
                <tr><td>Nama Bayi / Anak</td><td>: <strong>{{ $surat->pemohon }}</strong></td></tr>
                <tr><td>Keterangan</td><td>: {{ $surat->keperluan ?: 'Keterangan kelahiran' }}</td></tr>
            </table>
            <p>Telah lahir di wilayah RW {{ $rw }}, {{ $kel }}. Surat ini diberikan untuk keperluan pencatatan kelahiran dan administrasi kependudukan.</p>
            <p>Demikian surat keterangan kelahiran ini dibuat dengan sebenarnya.</p>
        @break

        @case('SKN')
            <p>Yang bertanda tangan di bawah ini, Ketua RW {{ $rw }} {{ $kel }}, Kecamatan {{ $kec }}, {{ $kab }}, dengan ini memberikan pengantar bahwa:</p>
            <table class="data-diri">
                <tr><td>Nama</td><td>: <strong>{{ $surat->pemohon }}</strong></td></tr>
                <tr><td>Keperluan</td><td>: {{ $surat->keperluan ?: 'Pengantar nikah ke KUA' }}</td></tr>
            </table>
            <p>Adalah benar warga RW {{ $rw }} yang bermaksud melangsungkan pernikahan. Sepanjang pengetahuan kami, yang bersangkutan berstatus belum menikah / duda / janda (coret yang tidak perlu) dan tidak ada halangan untuk melangsungkan pernikahan.</p>
            <p>Demikian surat pengantar nikah ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>
        @break

        @case('SKBB')
            <p>Yang bertanda tangan di bawah ini, Ketua RW {{ $rw }} {{ $kel }}, Kecamatan {{ $kec }}, {{ $kab }}, dengan ini menerangkan bahwa:</p>
            <table class="data-diri">
                <tr><td>Nama</td><td>: <strong>{{ $surat->pemohon }}</strong></td></tr>
                <tr><td>Keperluan</td><td>: {{ $surat->keperluan ?: 'Melamar pekerjaan / pendaftaran sekolah' }}</td></tr>
            </table>
            <p>Adalah benar warga RW {{ $rw }} yang sepanjang pengetahuan kami berkelakuan baik, sopan santun, dan tidak pernah melakukan perbuatan tercela selama berdomisili di wilayah kami.</p>
            <p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>
        @break

        @case('SKI')
            <p>Yang bertanda tangan di bawah ini, Ketua RW {{ $rw }} {{ $kel }}, Kecamatan {{ $kec }}, {{ $kab }}, dengan ini memberikan izin bahwa:</p>
            <table class="data-diri">
                <tr><td>Nama Pemohon</td><td>: <strong>{{ $surat->pemohon }}</strong></td></tr>
                <tr><td>Jenis Acara</td><td>: {{ $surat->keperluan ?: 'Hajatan / acara warga' }}</td></tr>
            </table>
            <p>Bermaksud menyelenggarakan acara di wilayah RW {{ $rw }}. Kami selaku pengurus RW telah melakukan koordinasi dan memberikan izin pelaksanaan dengan catatan agar menjaga ketertiban, keamanan, dan kenyamanan lingkungan sekitar.</p>
            <p>Demikian surat keterangan izin keramaian ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>
        @break

        @case('SKKB')
            <p>Yang bertanda tangan di bawah ini, Ketua RW {{ $rw }} {{ $kel }}, Kecamatan {{ $kec }}, {{ $kab }}, dengan ini menerangkan bahwa:</p>
            <table class="data-diri">
                <tr><td>Nama</td><td>: <strong>{{ $surat->pemohon }}</strong></td></tr>
                <tr><td>Barang Hilang</td><td>: {{ $surat->keperluan ?: 'Dokumen / barang berharga' }}</td></tr>
            </table>
            <p>Adalah benar warga RW {{ $rw }} yang menyatakan telah kehilangan barang sebagaimana tersebut di atas. Surat ini diberikan sebagai dasar pelaporan dan pengurusan dokumen pengganti.</p>
            <p>Demikian surat keterangan kehilangan ini dibuat dengan sebenarnya.</p>
        @break

        @case('SPB')
            <p>Yang bertanda tangan di bawah ini, Ketua RW {{ $rw }} {{ $kel }}, Kecamatan {{ $kec }}, {{ $kab }}, dengan ini memberikan pengantar bahwa:</p>
            <table class="data-diri">
                <tr><td>Nama</td><td>: <strong>{{ $surat->pemohon }}</strong></td></tr>
                <tr><td>Jenis Bantuan</td><td>: {{ $surat->keperluan ?: 'KIP / PKH / BPNT / Bantuan Pemerintah' }}</td></tr>
            </table>
            <p>Adalah benar warga RW {{ $rw }} yang bermaksud mengajukan bantuan sebagaimana tersebut di atas. Yang bersangkutan termasuk warga yang membutuhkan bantuan tersebut berdasarkan hasil musyawarah dan pendataan warga di tingkat RW.</p>
            <p>Demikian surat pengantar bantuan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>
        @break

        @default
            <p>Yang bertanda tangan di bawah ini, Ketua RW {{ $rw }} {{ $kel }}, Kecamatan {{ $kec }}, {{ $kab }}, dengan ini menerangkan bahwa:</p>
            <table class="data-diri">
                <tr><td>Nama</td><td>: <strong>{{ $surat->pemohon }}</strong></td></tr>
                <tr><td>Keperluan</td><td>: {{ $surat->keperluan ?: '-' }}</td></tr>
            </table>
            <p>Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>
        @endswitch

    </div>

    {{-- TANDA TANGAN --}}
    <div style="margin-top:40px;">
        <div style="text-align:right; margin-bottom:8px; font-size:14px;">
            {{ $kab }}, {{ \Carbon\Carbon::parse($surat->tanggal)->isoFormat('D MMMM Y') }}
        </div>

        @if($surat->rt_signed_by || $surat->rw_signed_by || $surat->sek_signed_by)
        {{-- Multi-signature layout for gradation surat --}}
        <div style="display:flex; justify-content:space-between; margin-top:24px; text-align:center; gap:20px; flex-wrap:wrap;">
            {{-- Mengetahui RT --}}
            <div style="flex:1; min-width:140px;">
                <div style="font-size:12px; margin-bottom:4px;">Mengetahui,</div>
                <div style="font-size:12px;">Ketua RT {{ $surat->rt_target ?? '' }}</div>
                <div style="margin-top:50px; border-bottom:1px solid #333; display:inline-block; padding-bottom:2px; font-weight:bold;">
                    {{ $surat->rt_signed_by ?? '_______________' }}
                </div>
                @if($surat->rt_signed_at)
                <div style="font-size:9px; color:#888; margin-top:2px;">{{ date('d/m/Y H:i', strtotime($surat->rt_signed_at)) }}</div>
                @endif
            </div>
            {{-- Mengetahui RW --}}
            <div style="flex:1; min-width:140px;">
                <div style="font-size:12px; margin-bottom:4px;">Mengetahui,</div>
                <div style="font-size:12px;">Ketua RW {{ $rw }}</div>
                <div style="margin-top:50px; border-bottom:1px solid #333; display:inline-block; padding-bottom:2px; font-weight:bold;">
                    {{ $surat->rw_signed_by ?? $ketua }}
                </div>
                @if($surat->rw_signed_at)
                <div style="font-size:9px; color:#888; margin-top:2px;">{{ date('d/m/Y H:i', strtotime($surat->rw_signed_at)) }}</div>
                @endif
            </div>
            {{-- Diketahui Sekretaris --}}
            <div style="flex:1; min-width:140px;">
                <div style="font-size:12px; margin-bottom:4px;">Diketahui,</div>
                <div style="font-size:12px;">Sekretaris RW {{ $rw }}</div>
                <div style="margin-top:50px; border-bottom:1px solid #333; display:inline-block; padding-bottom:2px; font-weight:bold;">
                    {{ $surat->sek_signed_by ?? '_______________' }}
                </div>
                @if($surat->sek_signed_at)
                <div style="font-size:9px; color:#888; margin-top:2px;">{{ date('d/m/Y H:i', strtotime($surat->sek_signed_at)) }}</div>
                @endif
            </div>
        </div>
        @else
        {{-- Original single signature for admin-created surat --}}
        <div class="ttd-area">
            <div class="ttd-box">
                Ketua RW {{ $rw }}<br>
                <div class="nama">{{ $ketua }}</div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
