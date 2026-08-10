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
        // Jiwa = kepala KK aktif + anggota (live count) — satu definisi, konsisten dgn Laporan & accessor
        $kkAktifIds = Keluarga::where('status', 'aktif')->pluck('keluarga_id');
        $totalJiwa = $totalKK + \App\Models\Anggota::whereIn('keluarga_id', $kkAktifIds)->count();

        // Saldo per kas
        $kasTypes = ['umum', 'sampah', 'padaringan'];
        $saldos = [];
        foreach ($kasTypes as $kas) {
            $masuk = Transaksi::where('kas', $kas)->whereYear('tanggal', $tahun)->where('jenis', 'masuk')->where('voided', false)->sum('jumlah');
            $keluar = Transaksi::where('kas', $kas)->whereYear('tanggal', $tahun)->where('jenis', 'keluar')->where('voided', false)->sum('jumlah');
            $saldos[$kas] = $masuk - $keluar;
        }
        $saldos['total'] = array_sum($saldos);

        // Saldo KUMULATIF (all-time, non-void) — saldo kas riil bersifat akumulatif lintas tahun,
        // bukan di-reset per tahun. Dipakai untuk kartu "Saldo Kas" agar akuntansinya benar.
        $saldoKumulatif = [];
        foreach ($kasTypes as $kas) {
            $m = Transaksi::where('kas', $kas)->where('jenis', 'masuk')->where('voided', false)->sum('jumlah');
            $k = Transaksi::where('kas', $kas)->where('jenis', 'keluar')->where('voided', false)->sum('jumlah');
            $saldoKumulatif[$kas] = $m - $k;
        }
        $saldoKumulatif['total'] = array_sum($saldoKumulatif);

        // Pemasukan/Pengeluaran bulan ini
        $pemasukanBulanIni = Transaksi::whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulanIni)->where('jenis', 'masuk')->where('voided', false)->sum('jumlah');
        $pengeluaranBulanIni = Transaksi::whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulanIni)->where('jenis', 'keluar')->where('voided', false)->sum('jumlah');

        // Bulan lalu — untuk delta MoM (tangani batas pergantian tahun)
        $blnLalu = $bulanIni == 1 ? 12 : $bulanIni - 1;
        $thnLalu = $bulanIni == 1 ? ((int)$tahun - 1) : $tahun;
        $pemasukanBulanLalu = Transaksi::whereYear('tanggal', $thnLalu)->whereMonth('tanggal', $blnLalu)->where('jenis', 'masuk')->where('voided', false)->sum('jumlah');
        $pengeluaranBulanLalu = Transaksi::whereYear('tanggal', $thnLalu)->whereMonth('tanggal', $blnLalu)->where('jenis', 'keluar')->where('voided', false)->sum('jumlah');

        // Tunggakan: KK yang ikut sampah tapi belum bayar bulan ini (any week)
        $kkSampah = Keluarga::where('status', 'aktif')->where('ikutSampah', true)->get();
        $kkBayarSampahIds = IuranSampah::where('tahun', $tahun)->get()->filter(function($i) use ($bulanKey) {
            $weeks = $i->weeks ?? [];
            foreach ($weeks as $key => $val) {
                if (str_starts_with($key, $bulanKey) && $val === 'lunas') return true;
            }
            return false;
        })->pluck('keluarga_id')->toArray();
        $tunggakanSampahIds = $kkSampah->pluck('keluarga_id')->diff($kkBayarSampahIds)->toArray();
        $tunggakanSampah = count($tunggakanSampahIds);

        $kkPadaringan = Keluarga::where('status', 'aktif')->where('ikutPadaringan', true)->get();
        $kkBayarPadaringanIds = IuranPadaringan::where('tahun', $tahun)->get()->filter(function($i) use ($bulanKey) {
            return ($i->months[$bulanKey] ?? false);
        })->pluck('keluarga_id')->toArray();
        $tunggakanPadaringanIds = $kkPadaringan->pluck('keluarga_id')->diff($kkBayarPadaringanIds)->toArray();
        $tunggakanPadaringan = count($tunggakanPadaringanIds);

        // DISTINCT KK yang menunggak di minimal 1 jenis iuran
        $tunggakanDistinct = count(array_unique(array_merge($tunggakanSampahIds, $tunggakanPadaringanIds)));

        // Tren Kas (6 bulan)
        $trenKas = [];
        $namaBulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $semester = $bulanIni <= 6 ? range(1, 6) : range(7, 12);
        foreach ($semester as $m) {
            $masuk = Transaksi::whereYear('tanggal', $tahun)->whereMonth('tanggal', $m)->where('jenis', 'masuk')->where('voided', false)->sum('jumlah');
            $keluar = Transaksi::whereYear('tanggal', $tahun)->whereMonth('tanggal', $m)->where('jenis', 'keluar')->where('voided', false)->sum('jumlah');
            $trenKas[] = ['bulan' => $namaBulan[$m], 'masuk' => (int)$masuk, 'keluar' => (int)$keluar];
        }

        // Tingkat Pembayaran per RT
        $rts = Keluarga::where('status', 'aktif')->select('rt')->distinct()->orderBy('rt')->pluck('rt');
        $pembayaranRT = [];
        $iuranSampahAll = IuranSampah::where('tahun', $tahun)->get()->keyBy('keluarga_id');
        foreach ($rts as $rt) {
            $kkRT = Keluarga::where('rt', $rt)->where('status', 'aktif')->where('ikutSampah', true)->get();
            $totalKKRT = $kkRT->count();
            $lunasRT = 0;
            foreach ($kkRT as $k) {
                $iur = $iuranSampahAll[$k->keluarga_id] ?? null;
                if ($iur) {
                    $weeks = $iur->weeks ?? [];
                    foreach ($weeks as $key => $val) {
                        if (str_starts_with($key, $bulanKey) && $val === 'lunas') { $lunasRT++; break; }
                    }
                }
            }
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

        // Demografi per RT
        $demografiRT = [];
        foreach ($rts as $rt) {
            $kkIdsRT = Keluarga::where('rt', $rt)->where('status', 'aktif')->pluck('keluarga_id');
            $jmlKK = $kkIdsRT->count();
            $jmlAnggota = \App\Models\Anggota::whereIn('keluarga_id', $kkIdsRT)->count();
            $demografiRT[] = [
                'rt' => $rt,
                'kk' => $jmlKK,
                'jiwa' => $jmlKK + $jmlAnggota, // konsisten dgn totalJiwa & Laporan
            ];
        }

        // Profil Sosial
        $allKK = Keluarga::where('status', 'aktif')->get();
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
        $agByKK = \App\Models\Anggota::whereIn('keluarga_id', $kkAktifIds)->get()->groupBy('keluarga_id');
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

        $tarifSampah = AppSetting::where('key', 'tarif_sampah')->value('value') ?? 5000;

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
