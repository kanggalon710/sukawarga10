<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Keluarga;
use App\Models\Transaksi;
use App\Models\Aduan;
use App\Models\AppSetting;
use App\Models\IuranSampah;
use App\Models\IuranPadaringan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $tahun = request('tahun', date('Y'));
        $bulanIni = (int) date('m');
        $bulanAll = ['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGU','SEP','OKT','NOV','DES'];
        $bulanKey = $bulanAll[$bulanIni - 1] ?? 'JAN';

        // Core stats
        $totalKK = Keluarga::where('status', 'aktif')->count();
        $kkAktifIds = Keluarga::where('status', 'aktif')->pluck('keluarga_id');

        // Seluruh anggota KK aktif diambil SEKALI lalu dipakai ulang untuk jiwa,
        // demografi per RT, dan indeks kesejahteraan di bawah.
        $agByKK = \App\Models\Anggota::whereIn('keluarga_id', $kkAktifIds)
            ->get(['keluarga_id', 'pekerjaan', 'statusPekerjaan', 'penghasilan'])
            ->groupBy('keluarga_id');

        // Jiwa = kepala KK aktif + anggota — satu definisi, konsisten dgn Laporan & accessor
        $totalJiwa = $totalKK + $agByKK->flatten()->count();

        // --- Agregat transaksi ---
        // Tiga query menggantikan ~24 query yang sebelumnya dijalankan di dalam loop
        // (per jenis kas, per bulan). Baris tahun berjalan dipakai ulang untuk saldo,
        // ringkasan bulanan, dan grafik tren.
        $kasTypes = ['umum', 'sampah', 'padaringan'];

        $ringkasSaldo = function ($rows) use ($kasTypes) {
            $hasil = [];
            foreach ($kasTypes as $kas) {
                $baris = $rows->where('kas', $kas);
                $hasil[$kas] = (int) $baris->where('jenis', 'masuk')->sum('jumlah')
                             - (int) $baris->where('jenis', 'keluar')->sum('jumlah');
            }
            $hasil['total'] = array_sum($hasil);
            return $hasil;
        };

        $trxTahunIni = Transaksi::where('voided', false)->whereYear('tanggal', $tahun)
            ->get(['tanggal', 'jenis', 'kas', 'jumlah']);

        $saldos = $ringkasSaldo($trxTahunIni);

        // Saldo KUMULATIF (all-time, non-void) — saldo kas riil bersifat akumulatif lintas tahun,
        // bukan di-reset per tahun. Dipakai untuk kartu "Saldo Kas" agar akuntansinya benar.
        $saldoKumulatif = $ringkasSaldo(
            Transaksi::where('voided', false)
                ->selectRaw('kas, jenis, SUM(jumlah) as jumlah')
                ->groupBy('kas', 'jenis')->get()
        );

        // Ringkasan per bulan dari baris yang sudah diambil
        $totalBulan = fn($rows, $bulan, $jenis) => (int) $rows
            ->where('jenis', $jenis)
            ->filter(fn($t) => (int) $t->tanggal->month === (int) $bulan)
            ->sum('jumlah');

        $pemasukanBulanIni = $totalBulan($trxTahunIni, $bulanIni, 'masuk');
        $pengeluaranBulanIni = $totalBulan($trxTahunIni, $bulanIni, 'keluar');

        // Bulan lalu — untuk delta MoM (tangani batas pergantian tahun)
        $blnLalu = $bulanIni == 1 ? 12 : $bulanIni - 1;
        $thnLalu = $bulanIni == 1 ? ((int)$tahun - 1) : $tahun;
        $trxBulanLalu = $thnLalu == $tahun
            ? $trxTahunIni
            : Transaksi::where('voided', false)->whereYear('tanggal', $thnLalu)
                ->get(['tanggal', 'jenis', 'kas', 'jumlah']);
        $pemasukanBulanLalu = $totalBulan($trxBulanLalu, $blnLalu, 'masuk');
        $pengeluaranBulanLalu = $totalBulan($trxBulanLalu, $blnLalu, 'keluar');

        // Data iuran tahun berjalan diambil sekali, dipakai untuk tunggakan DAN
        // tingkat pembayaran per RT di bawah (sebelumnya di-query dua kali).
        $iuranSampahAll = IuranSampah::where('tahun', $tahun)->get()->keyBy('keluarga_id');

        // Satu definisi "sudah lunas bulan ini", dipakai di semua perhitungan sampah.
        $lunasBulanIni = function ($keluargaId) use ($iuranSampahAll, $bulanKey) {
            $iur = $iuranSampahAll[$keluargaId] ?? null;
            if (!$iur) return false;
            foreach ($iur->weeks ?? [] as $key => $val) {
                if (str_starts_with($key, $bulanKey) && $val === 'lunas') return true;
            }
            return false;
        };

        // Tunggakan: KK yang ikut sampah tapi belum bayar bulan ini (any week)
        $kkSampah = Keluarga::where('status', 'aktif')->where('ikutSampah', true)->get();
        $tunggakanSampahIds = $kkSampah
            ->reject(fn($k) => $lunasBulanIni($k->keluarga_id))
            ->pluck('keluarga_id')->values()->toArray();
        $tunggakanSampah = count($tunggakanSampahIds);

        $kkPadaringan = Keluarga::where('status', 'aktif')->where('ikutPadaringan', true)->get();
        $kkBayarPadaringanIds = IuranPadaringan::where('tahun', $tahun)->get()->filter(function($i) use ($bulanKey) {
            return ($i->months[$bulanKey] ?? false);
        })->pluck('keluarga_id')->toArray();
        $tunggakanPadaringanIds = $kkPadaringan->pluck('keluarga_id')->diff($kkBayarPadaringanIds)->toArray();
        $tunggakanPadaringan = count($tunggakanPadaringanIds);

        // DISTINCT KK yang menunggak di minimal 1 jenis iuran
        $tunggakanDistinct = count(array_unique(array_merge($tunggakanSampahIds, $tunggakanPadaringanIds)));

        // Tren Kas (6 bulan) — dari baris yang sudah diambil di atas, tanpa query per bulan
        $trenKas = [];
        $namaBulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $semester = $bulanIni <= 6 ? range(1, 6) : range(7, 12);
        foreach ($semester as $m) {
            $trenKas[] = [
                'bulan' => $namaBulan[$m],
                'masuk' => $totalBulan($trxTahunIni, $m, 'masuk'),
                'keluar' => $totalBulan($trxTahunIni, $m, 'keluar'),
            ];
        }

        // Tingkat Pembayaran per RT — dikelompokkan dari koleksi $kkSampah yang sudah
        // diambil di atas, bukan satu query per RT.
        $rts = Keluarga::where('status', 'aktif')->select('rt')->distinct()->orderBy('rt')->pluck('rt');
        $pembayaranRT = [];
        $kkSampahByRT = $kkSampah->groupBy('rt');
        foreach ($rts as $rt) {
            $kkRT = $kkSampahByRT->get($rt, collect());
            $totalKKRT = $kkRT->count();
            $lunasRT = $kkRT->filter(fn($k) => $lunasBulanIni($k->keluarga_id))->count();
            $pembayaranRT[] = [
                'rt' => $rt, 'total_kk' => $totalKKRT, 'lunas' => $lunasRT,
                'persentase' => $totalKKRT > 0 ? round($lunasRT / $totalKKRT * 100) : 0,
            ];
        }

        // Collection Rate sampah keseluruhan + denominator peserta (basis untuk KPI tunggakan)
        $totalPesertaSampah = collect($pembayaranRT)->sum('total_kk');
        $totalLunasSampah   = collect($pembayaranRT)->sum('lunas');
        $collectionRateSampah = $totalPesertaSampah > 0 ? round($totalLunasSampah / $totalPesertaSampah * 100) : 0;
        // Total peserta unik (sampah ∪ padaringan) — denominator untuk Tunggakan Rate
        $totalPesertaUnik = $kkSampah->pluck('keluarga_id')->merge($kkPadaringan->pluck('keluarga_id'))->unique()->count();

        // Runway kas ≈ saldo kumulatif / rata-rata pengeluaran bulanan (6 bulan terakhir)
        $sejak6Bulan = Carbon::create((int)$tahun, $bulanIni, 1)->subMonths(5)->toDateString();
        $pengeluaran6bln = Transaksi::where('jenis', 'keluar')->where('voided', false)
            ->where('tanggal', '>=', $sejak6Bulan)->sum('jumlah');
        $avgPengeluaranBulanan = $pengeluaran6bln / 6;
        $runwayBulan = $avgPengeluaranBulanan > 0 ? round($saldoKumulatif['total'] / $avgPengeluaranBulanan, 1) : null;

        // Profil Sosial — seluruh KK aktif diambil sekali, dipakai juga oleh
        // demografi per RT dan indeks kesejahteraan di bawah.
        $allKK = Keluarga::where('status', 'aktif')->get();

        // Demografi per RT — dikelompokkan di memori, bukan 2 query per RT
        $allKKByRT = $allKK->groupBy('rt');
        $demografiRT = [];
        foreach ($rts as $rt) {
            $kkRT = $allKKByRT->get($rt, collect());
            $jmlKK = $kkRT->count();
            $jmlAnggota = $kkRT->sum(fn($k) => $agByKK->get($k->keluarga_id, collect())->count());
            $demografiRT[] = [
                'rt' => $rt,
                'kk' => $jmlKK,
                'jiwa' => $jmlKK + $jmlAnggota, // konsisten dgn totalJiwa & Laporan
            ];
        }

        $bansosCount = 0; $rentanCount = 0;
        foreach ($allKK as $k) {
            $bansos = $k->bansos ?? [];
            if (collect($bansos)->filter()->count() > 0) $bansosCount++;
            $rentan = $k->kelompokRentan ?? [];
            if (collect($rentan)->filter()->count() > 0) $rentanCount++;
        }
        $profilSosial = [
            'bansos' => $bansosCount,
            'rentan' => $rentanCount,
            'umkm' => $allKK->filter(fn($k) => in_array('umkm', $k->tags ?? []))->count(),
            'kurang_mampu' => $allKK->filter(fn($k) => in_array('kurang_mampu', $k->tags ?? []))->count(),
        ];

        // ============================================
        // INDEKS KESEJAHTERAAN & SOSIAL (untuk keputusan) — dari kolom kanonik
        // ============================================
        $rtlh = 0; $rentanEkonomi = 0; $sanitasiTakLayak = 0; $bpjsCov = 0; $airAman = 0; $inklusiKeuangan = 0;
        $tanpaPekerja = 0; $bawahGaris = 0;
        $garis = garisKemiskinan();
        // $agByKK sudah diambil di awal method — tidak di-query ulang di sini.
        $dindingTakLayak = ['Bambu', 'Papan', 'Tembok tdk/plester'];
        foreach ($allKK as $k) {
            // Ekonomi rumah tangga: hitung SEMUA pekerja dalam KK (bukan hanya kepala keluarga)
            $members = $agByKK->get($k->keluarga_id, collect());
            $earners = kkKepalaBekerja($k) ? 1 : 0;
            $income = incomeMidpoint($k->penghasilan);
            foreach ($members as $a) {
                $st = $a->statusPekerjaan ?: statusKerjaDariPekerjaan($a->pekerjaan);
                if ($st === 'Bekerja') { $earners++; $income += incomeMidpoint($a->penghasilan); }
            }
            if ($earners === 0) $tanpaPekerja++;
            if ($income > 0 && ($income / (1 + $members->count())) < $garis) $bawahGaris++;
            // Rumah Tidak Layak Huni (indikasi): non-permanen / lantai tanah / dinding tidak layak
            if (trim($k->tipeBangunan ?? '') === 'Non permanen'
                || trim($k->bahanLantai ?? '') === 'Tanah'
                || in_array(trim($k->bahanDinding ?? ''), $dindingTakLayak)) $rtlh++;
            // Rentan ekonomi: penghasilan < 1 juta DAN tidak punya tabungan
            if (($k->penghasilan === '< 1 Juta') && !$k->punyaTabungan) $rentanEkonomi++;
            // Sanitasi tidak layak: tinja ke sungai / tidak punya jamban / jamban bersama
            if (in_array(trim($k->pembuanganTinja ?? ''), ['Sungai'])
                || in_array(trim($k->kepemilikanJamban ?? ''), ['Tidak punya', 'Jamban bersama'])) $sanitasiTakLayak++;
            // Cakupan BPJS (dari map bansos)
            if (!empty(($k->bansos ?? [])['bpjs'])) $bpjsCov++;
            // Akses air minum dari sumber terlindungi
            if (in_array(trim($k->sumberAirMinum ?? ''), ['PDAM', 'Sumur pompa', 'Sumur gali'])) $airAman++;
            // Inklusi keuangan: punya akses kredit formal/koperasi
            if (!empty($k->aksesKredit) && trim($k->aksesKredit) !== 'Tidak Ada') $inklusiKeuangan++;
        }
        $kesejahteraan = [
            'total' => $allKK->count(),
            'rtlh' => $rtlh,
            'rentanEkonomi' => $rentanEkonomi,
            'sanitasiTakLayak' => $sanitasiTakLayak,
            'bpjsCov' => $bpjsCov,
            'airAman' => $airAman,
            'inklusiKeuangan' => $inklusiKeuangan,
            'tanpaPekerja' => $tanpaPekerja,
            'bawahGaris' => $bawahGaris,
            'garis' => $garis,
        ];

        // Recent transactions
        $recentTrx = Transaksi::where('voided', false)->orderByDesc('tanggal')->orderByDesc('created_at')->limit(5)->get();

        $tarifSampah = AppSetting::nilai('tarif_sampah') ?? 5000;

        return view('dashboard.index', compact(
            'totalKK', 'totalJiwa', 'saldos', 'saldoKumulatif', 'tahun', 'trenKas', 'pembayaranRT',
            'pemasukanBulanIni', 'pengeluaranBulanIni', 'pemasukanBulanLalu', 'pengeluaranBulanLalu',
            'tarifSampah', 'bulanIni',
            'tunggakanSampah', 'tunggakanPadaringan', 'tunggakanDistinct', 'demografiRT', 'profilSosial',
            'recentTrx', 'bulanKey', 'namaBulan',
            'collectionRateSampah', 'totalPesertaSampah', 'totalLunasSampah', 'totalPesertaUnik',
            'runwayBulan', 'avgPengeluaranBulanan', 'kesejahteraan'
        ));
    }
}
