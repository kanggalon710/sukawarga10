<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\Keluarga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    public function index(Request $request, $tab = null)
    {
        $tahun = $request->get('tahun', date('Y'));
        // Tab dari segmen URL (/laporan/{tab}); fallback query-string utk kompatibilitas.
        $tab = $tab ?: $request->get('tab', 'ringkasan');
        $butuhDemografi = in_array($tab, ['demografi', 'ekonomi', 'permukiman']);

        // Ranking RT - combine direct transaksi refs + iuran data per RT
        $rankingRT = collect();
        try {
            $allMasuk = DB::table('transaksis')
                ->where('jenis', 'masuk')
                ->where('voided', false)
                ->whereYear('tanggal', $tahun)
                ->get();
            $allKK = Keluarga::where('status', 'aktif')->get()->keyBy('keluarga_id');
            $rtTotals = [];
            foreach ($allMasuk as $trx) {
                $rt = null;
                if ($trx->refKeluargaId && isset($allKK[$trx->refKeluargaId])) {
                    $rt = $allKK[$trx->refKeluargaId]->rt;
                }
                if ($rt) {
                    $rtTotals[$rt] = ($rtTotals[$rt] ?? 0) + $trx->jumlah;
                }
            }
            $rankingRT = collect($rtTotals)->map(fn($total, $rt) => (object)['rt' => $rt, 'total' => $total])
                ->sortByDesc('total')->values();
        } catch (\Exception $e) {}

        // Summary bulanan — portable lintas MySQL & SQLite (whereMonth, bukan raw MONTH())
        $bulanData = [];
        $namaBulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        for ($m = 1; $m <= 12; $m++) {
            $masuk = Transaksi::whereYear('tanggal', $tahun)->whereMonth('tanggal', $m)->where('jenis', 'masuk')->where('voided', false)->sum('jumlah');
            $keluar = Transaksi::whereYear('tanggal', $tahun)->whereMonth('tanggal', $m)->where('jenis', 'keluar')->where('voided', false)->sum('jumlah');
            $bulanData[] = ['bulan' => $namaBulan[$m], 'masuk' => (int) $masuk, 'keluar' => (int) $keluar];
        }

        $totalMasuk = Transaksi::whereYear('tanggal', $tahun)->where('jenis', 'masuk')->where('voided', false)->sum('jumlah');
        $totalKeluar = Transaksi::whereYear('tanggal', $tahun)->where('jenis', 'keluar')->where('voided', false)->sum('jumlah');

        // ============================================
        // DEMOGRAFI DATA (Deep Analytics)
        // ============================================
        $demografi = [];
        if ($butuhDemografi) {
            $keluargas = Keluarga::where('status', 'aktif')->withCount('anggota')->get();
            $anggotas = \App\Models\Anggota::all();

            // === 1. POPULASI & KOMPOSISI PER RT ===
            $populasiRT = [];
            foreach ($keluargas as $kk) {
                $rt = $kk->rt;
                if (!isset($populasiRT[$rt])) {
                    $populasiRT[$rt] = ['rt' => $rt, 'kk' => 0, 'jiwa' => 0, 'laki' => 0, 'perempuan' => 0];
                }
                $populasiRT[$rt]['kk']++;
                $populasiRT[$rt]['jiwa'] += 1 + ($kk->anggota_count ?? 0);
            }
            // Count L/P per RT from anggotas
            foreach ($anggotas as $a) {
                $keluarga = $keluargas->firstWhere('keluarga_id', $a->keluarga_id);
                if ($keluarga && isset($populasiRT[$keluarga->rt])) {
                    if (strtolower(substr($a->jenisKelamin ?? '', 0, 1)) === 'l') {
                        $populasiRT[$keluarga->rt]['laki']++;
                    } else {
                        $populasiRT[$keluarga->rt]['perempuan']++;
                    }
                }
            }
            ksort($populasiRT);
            $demografi['populasiRT'] = array_values($populasiRT);
            $demografi['totalKK'] = $keluargas->count();
            $demografi['totalJiwa'] = collect($populasiRT)->sum('jiwa');
            $demografi['totalLaki'] = collect($populasiRT)->sum('laki');
            $demografi['totalPerempuan'] = collect($populasiRT)->sum('perempuan');

            // === 2. KONDISI RUMAH & SANITASI (Deep) ===
            $statusRumah = $keluargas->groupBy('statusRumah')->map->count()->sortDesc();
            $sanitasi = [
                'air_minum' => [], 'air_mandi' => [], 'jamban' => [],
                'tinja' => [], 'sampah' => [], 'masak' => [],
                'lantai' => [], 'dinding' => [], 'atap' => [],
                'tipe_bangunan' => [],
            ];
            $luasTotal = 0; $luasCount = 0; $kamarTotal = 0; $kamarCount = 0;
            foreach ($keluargas as $kk) {
                // Baca dari KOLOM DB (sumber kanonik yang ditulis form), BUKAN dari JSON aset.
                $fields = [
                    'air_minum' => 'sumberAirMinum', 'air_mandi' => 'sumberAirMandi',
                    'jamban' => 'kepemilikanJamban', 'tinja' => 'pembuanganTinja', 'sampah' => 'caraBuangSampah',
                    'masak' => 'sumberMasak', 'lantai' => 'bahanLantai',
                    'dinding' => 'bahanDinding', 'atap' => 'bahanAtap',
                    'tipe_bangunan' => 'tipeBangunan',
                ];
                foreach ($fields as $key => $col) {
                    $val = !empty($kk->$col) ? trim($kk->$col) : null;
                    if ($val) {
                        $sanitasi[$key][$val] = ($sanitasi[$key][$val] ?? 0) + 1;
                    }
                }
                // Luas bisa berformat koma desimal ("4,85") — normalisasi sebelum hitung
                $luas = $kk->luasLantai !== null ? str_replace(',', '.', (string)$kk->luasLantai) : '';
                if ($luas !== '' && is_numeric($luas)) {
                    $luasTotal += (float)$luas; $luasCount++;
                }
                if (!empty($kk->jmlKamarTidur) && is_numeric($kk->jmlKamarTidur)) {
                    $kamarTotal += (int)$kk->jmlKamarTidur; $kamarCount++;
                }
            }
            foreach ($sanitasi as &$s) arsort($s);
            $demografi['statusRumah'] = $statusRumah;
            $demografi['sanitasi'] = $sanitasi;
            $demografi['avgLuas'] = $luasCount > 0 ? round($luasTotal / $luasCount) : 0;
            $demografi['avgKamar'] = $kamarCount > 0 ? round($kamarTotal / $kamarCount, 1) : 0;
            $demografi['luasCount'] = $luasCount;
            $demografi['kamarCount'] = $kamarCount;

            // === 3. EKONOMI & KESEJAHTERAAN ===
            $pekerjaan = $keluargas->groupBy('pekerjaan')->map->count()->sortDesc();
            $penghasilan = $keluargas->groupBy('penghasilan')->map->count()->sortDesc();
            $demografi['pekerjaan'] = $pekerjaan;
            $demografi['penghasilan'] = $penghasilan;
            $demografi['punyaTabungan'] = $keluargas->where('punyaTabungan', true)->count();
            $demografi['punyaHutang'] = $keluargas->where('punyaHutangUsaha', true)->count();

            // === 3B. EKONOMI RUMAH TANGGA — individu → agregat KK (bukan hanya kepala keluarga) ===
            $garis = garisKemiskinan();
            $anggotaByKK = $anggotas->groupBy('keluarga_id');
            $statusKerjaDist = [];
            $pekerjaPerKK = ['Tanpa Pekerja' => 0, '1 Pekerja' => 0, '2 Pekerja' => 0, '3+ Pekerja' => 0];
            $perKapitaDist = ['< Rp 500 rb (di bawah garis)' => 0, 'Rp 500 rb – 1 jt' => 0, 'Rp 1 – 2 jt' => 0, '> Rp 2 jt' => 0];
            $totalPekerja = 0; $kkTanpaPekerja = 0; $mencariKerja = 0;
            $pekerjaTanpaPenghasilan = 0; $kkBelumTerdataIncome = 0; $bawahGaris = 0;
            foreach ($keluargas as $kkx) {
                $members = $anggotaByKK->get($kkx->keluarga_id, collect());
                $earners = 0;
                $income = incomeMidpoint($kkx->penghasilan); // penghasilan kepala keluarga
                $kkWork = kkKepalaBekerja($kkx);
                $statusKerjaDist[$kkWork ? 'Bekerja' : 'Tidak Bekerja'] = ($statusKerjaDist[$kkWork ? 'Bekerja' : 'Tidak Bekerja'] ?? 0) + 1;
                if ($kkWork) { $earners++; $totalPekerja++; }
                foreach ($members as $a) {
                    $st = $a->statusPekerjaan ?: statusKerjaDariPekerjaan($a->pekerjaan);
                    $statusKerjaDist[$st] = ($statusKerjaDist[$st] ?? 0) + 1;
                    if ($st === 'Mencari Kerja') $mencariKerja++;
                    if ($st === 'Bekerja') {
                        $earners++; $totalPekerja++;
                        $mi = incomeMidpoint($a->penghasilan);
                        if ($mi > 0) $income += $mi; else $pekerjaTanpaPenghasilan++;
                    }
                }
                if ($earners === 0) { $kkTanpaPekerja++; $pekerjaPerKK['Tanpa Pekerja']++; }
                elseif ($earners === 1) $pekerjaPerKK['1 Pekerja']++;
                elseif ($earners === 2) $pekerjaPerKK['2 Pekerja']++;
                else $pekerjaPerKK['3+ Pekerja']++;
                $jiwa = 1 + $members->count();
                if ($income > 0) {
                    $pc = $income / $jiwa;
                    if ($pc < $garis) $bawahGaris++;
                    if ($pc < 500000) $perKapitaDist['< Rp 500 rb (di bawah garis)']++;
                    elseif ($pc < 1000000) $perKapitaDist['Rp 500 rb – 1 jt']++;
                    elseif ($pc < 2000000) $perKapitaDist['Rp 1 – 2 jt']++;
                    else $perKapitaDist['> Rp 2 jt']++;
                } else {
                    $kkBelumTerdataIncome++;
                }
            }
            arsort($statusKerjaDist);
            $demografi['ekonomiRT'] = [
                'garis' => $garis,
                'statusKerja' => $statusKerjaDist,
                'pekerjaPerKK' => $pekerjaPerKK,
                'perKapita' => $perKapitaDist,
                'totalPekerja' => $totalPekerja,
                'rataPekerjaPerKK' => $keluargas->count() > 0 ? round($totalPekerja / $keluargas->count(), 1) : 0,
                'kkTanpaPekerja' => $kkTanpaPekerja,
                'mencariKerja' => $mencariKerja,
                'pekerjaTanpaPenghasilan' => $pekerjaTanpaPenghasilan,
                'kkBelumTerdataIncome' => $kkBelumTerdataIncome,
                'bawahGaris' => $bawahGaris,
                'totalIndividu' => $keluargas->count() + $anggotas->count(),
            ];

            // === 4. PARTISIPASI IURAN WARGA ===
            $demografi['ikutSampah'] = $keluargas->where('ikutSampah', true)->count();
            $demografi['ikutPadaringan'] = $keluargas->where('ikutPadaringan', true)->count();

            // === 4B. DISTRIBUSI SERTIFIKAT TANAH ===
            $sertifikatDist = $keluargas->filter(fn($k) => !empty($k->jenisSertifikat))
                ->groupBy('jenisSertifikat')->map->count()->sortDesc();
            $demografi['sertifikat'] = $sertifikatDist;
            $demografi['sertifikatTotal'] = $sertifikatDist->sum();

            // === 5. CAKUPAN BANSOS & JAMINAN SOSIAL ===
            // bansos disimpan sebagai map boolean key kecil {pkh,bpnt,blt,rutilahu,kis,kip,bpjs}
            $bansosLabels = ['pkh' => 'PKH', 'bpnt' => 'BPNT', 'blt' => 'BLT', 'rutilahu' => 'Rutilahu', 'kis' => 'KIS PBI', 'kip' => 'KIP'];
            $bansosCount = ['PKH' => 0, 'BPNT' => 0, 'BLT' => 0, 'Rutilahu' => 0, 'KIS PBI' => 0, 'KIP' => 0];
            foreach ($keluargas as $kk) {
                $b = $kk->bansos ?? [];
                foreach ($bansosLabels as $key => $label) {
                    if (!empty($b[$key])) $bansosCount[$label]++;
                }
            }
            $demografi['bansos'] = $bansosCount;
            $demografi['bansosTotal'] = $keluargas->count();

            // BPJS from anggotas
            $bpjsStats = $anggotas->groupBy('statusBPJS')->map->count()->sortDesc();
            $demografi['bpjs'] = $bpjsStats;
            $demografi['bpjsTotal'] = $anggotas->count();

            // === 6. PEKERJAAN ANGGOTA ===
            $pekerjaanAnggota = $anggotas->groupBy('pekerjaan')->map->count()->sortDesc();
            $demografi['pekerjaanAnggota'] = $pekerjaanAnggota;

            // === Gender composition ===
            $genderAnggota = $anggotas->groupBy('jenisKelamin')->map->count();
            $demografi['genderAnggota'] = $genderAnggota;

            // === Pendidikan, Status Perkawinan & Kesehatan (Tahap E) ===
            $demografi['pendidikanAnggota'] = $anggotas->filter(fn($a) => !empty($a->pendidikan))->groupBy('pendidikan')->map->count()->sortDesc();
            $demografi['statusKawin'] = $anggotas->filter(fn($a) => !empty($a->statusPerkawinan))->groupBy('statusPerkawinan')->map->count()->sortDesc();
            $penyakit = []; $kbCount = 0; $stuntingCount = 0;
            foreach ($keluargas as $kkx) {
                $kes = $kkx->kesehatan ?? [];
                foreach (($kes['penyakitKronis'] ?? []) as $p) { $penyakit[$p] = ($penyakit[$p] ?? 0) + 1; }
                if (!empty($kes['ikutKB'])) $kbCount++;
                if (!empty($kes['stunting'])) $stuntingCount++;
            }
            arsort($penyakit);
            $demografi['penyakitKronis'] = $penyakit;
            $demografi['ikutKB'] = $kbCount;
            $demografi['stunting'] = $stuntingCount;

            // =============================================
            // === 7. PIRAMIDA PENDUDUK & ANALISIS USIA ===
            // =============================================
            $today = now();
            $ageCategories = [
                'Balita (0-4)' => [0, 4],
                'Anak-anak (5-11)' => [5, 11],
                'Remaja (12-17)' => [12, 17],
                'Dewasa Muda (18-25)' => [18, 25],
                'Usia Produktif (26-45)' => [26, 45],
                'Pra-Lansia (46-59)' => [46, 59],
                'Lansia (60+)' => [60, 200],
            ];

            // Initialize pyramid: each category has L and P counts
            $piramida = [];
            foreach ($ageCategories as $label => $range) {
                $piramida[$label] = ['L' => 0, 'P' => 0, 'total' => 0];
            }

            $allAges = []; // Collect all ages for stats
            $withBirthDate = 0;
            $agePerRT = []; // per RT breakdown

            foreach ($anggotas as $a) {
                if (!$a->tanggalLahir) continue;
                $withBirthDate++;
                $age = \Carbon\Carbon::parse($a->tanggalLahir)->age;
                $allAges[] = $age;
                $gender = strtolower(substr($a->jenisKelamin ?? '', 0, 1)) === 'l' ? 'L' : 'P';

                // Classify into age category
                foreach ($ageCategories as $label => $range) {
                    if ($age >= $range[0] && $age <= $range[1]) {
                        $piramida[$label][$gender]++;
                        $piramida[$label]['total']++;
                        break;
                    }
                }

                // Per RT
                $keluarga = $keluargas->firstWhere('keluarga_id', $a->keluarga_id);
                if ($keluarga) {
                    $rt = $keluarga->rt;
                    if (!isset($agePerRT[$rt])) {
                        $agePerRT[$rt] = ['balita' => 0, 'anak' => 0, 'remaja' => 0, 'produktif' => 0, 'lansia' => 0];
                    }
                    if ($age <= 4) $agePerRT[$rt]['balita']++;
                    elseif ($age <= 11) $agePerRT[$rt]['anak']++;
                    elseif ($age <= 17) $agePerRT[$rt]['remaja']++;
                    elseif ($age <= 59) $agePerRT[$rt]['produktif']++;
                    else $agePerRT[$rt]['lansia']++;
                }
            }

            // Include KK ages in the pyramid
            foreach ($keluargas as $kk) {
                if (!$kk->tanggalLahirKK) continue;
                $withBirthDate++;
                $age = $kk->tanggalLahirKK->age;
                $allAges[] = $age;
                $gender = strtolower(substr($kk->jenisKelaminKK ?? 'L', 0, 1)) === 'p' ? 'P' : 'L';

                foreach ($ageCategories as $label => $range) {
                    if ($age >= $range[0] && $age <= $range[1]) {
                        $piramida[$label][$gender]++;
                        $piramida[$label]['total']++;
                        break;
                    }
                }

                $rt = $kk->rt;
                if (!isset($agePerRT[$rt])) {
                    $agePerRT[$rt] = ['balita' => 0, 'anak' => 0, 'remaja' => 0, 'produktif' => 0, 'lansia' => 0];
                }
                if ($age <= 4) $agePerRT[$rt]['balita']++;
                elseif ($age <= 11) $agePerRT[$rt]['anak']++;
                elseif ($age <= 17) $agePerRT[$rt]['remaja']++;
                elseif ($age <= 59) $agePerRT[$rt]['produktif']++;
                else $agePerRT[$rt]['lansia']++;
            }

            ksort($agePerRT);

            // Age statistics
            sort($allAges);
            $demografi['piramida'] = $piramida;
            $demografi['withBirthDate'] = $withBirthDate;
            $demografi['agePerRT'] = $agePerRT;
            $demografi['avgAge'] = count($allAges) > 0 ? round(array_sum($allAges) / count($allAges), 1) : 0;
            $demografi['medianAge'] = count($allAges) > 0 ? $allAges[(int)floor(count($allAges) / 2)] : 0;
            $demografi['youngestAge'] = count($allAges) > 0 ? min($allAges) : 0;
            $demografi['oldestAge'] = count($allAges) > 0 ? max($allAges) : 0;

            // Dependency ratio: (non-produktif / produktif) × 100
            $nonProduktif = ($piramida['Balita (0-4)']['total'] ?? 0) + ($piramida['Anak-anak (5-11)']['total'] ?? 0)
                + ($piramida['Remaja (12-17)']['total'] ?? 0) + ($piramida['Lansia (60+)']['total'] ?? 0);
            $produktif = ($piramida['Dewasa Muda (18-25)']['total'] ?? 0) + ($piramida['Usia Produktif (26-45)']['total'] ?? 0)
                + ($piramida['Pra-Lansia (46-59)']['total'] ?? 0);
            $demografi['nonProduktif'] = $nonProduktif;
            $demografi['produktif'] = $produktif;
            $demografi['dependencyRatio'] = $produktif > 0 ? round($nonProduktif / $produktif * 100) : 0;
        }

        return view('laporan.index', compact('tahun', 'tab', 'rankingRT', 'bulanData', 'totalMasuk', 'totalKeluar', 'demografi'));
    }
}
