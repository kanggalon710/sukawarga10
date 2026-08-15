@extends('layouts.app')
@section('title', 'Pengaturan')
@section('page-title', 'Pengaturan')
@section('page-subtitle', 'Konfigurasi aplikasi ' . namaAplikasi())

@section('content')
<div class="toolbar">
    <div class="toolbar-left">
        <button class="btn btn-primary btn-sm" id="tabTarif" onclick="showTab('tarif')">Tarif & Umum</button>
        <button class="btn btn-outline btn-sm" id="tabInfo" onclick="showTab('info')">Info RW</button>
        <button class="btn btn-outline btn-sm" id="tabWa" onclick="showTab('wa')">WhatsApp API</button>
        <button class="btn btn-outline btn-sm" id="tabData" onclick="showTab('data')">Data & Backup</button>
    </div>
</div>

<form method="POST" action="{{ route('pengaturan.update') }}">
@csrf
<input type="hidden" name="_active_tab" id="activeTabInput" value="tarif">

<!-- Tab Tarif -->
<div class="card" id="panelTarif">
    <div class="card-header"><div class="card-title"><i class="fas fa-coins" style="color:var(--emas);"></i> Tarif & Umum</div></div>
    <div class="card-sub">Konfigurasi tarif iuran dan pengaturan umum</div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:16px;">
        <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Tahun Aktif</label><input type="number" name="tahun_aktif" value="{{ $settings['tahun_aktif'] ?? date('Y') }}" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
        <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Nama Operator</label><input type="text" name="nama_operator" value="{{ $settings['nama_operator'] ?? '' }}" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;" placeholder="Nama operator"></div>
        <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Tarif Sampah (Rp/minggu)</label><input type="number" name="tarif_sampah" value="{{ $settings['tarif_sampah'] ?? 5000 }}" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
        <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Tarif Padaringan (Rp/bulan)</label><input type="number" name="tarif_padaringan" value="{{ $settings['tarif_padaringan'] ?? 15000 }}" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
    </div>
</div>

<!-- Tab Info RW -->
<div class="card" id="panelInfo" style="display:none;">
    <div class="card-header"><div class="card-title"><i class="fas fa-signature" style="color:var(--biru);" aria-hidden="true"></i> Identitas Aplikasi</div></div>
    <div class="card-sub">Nama yang tampil di halaman login, sidebar, judul halaman, dan seluruh pesan WhatsApp ke warga</div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:16px;">
        <div><label for="nama_aplikasi" style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Nama Aplikasi</label><input type="text" id="nama_aplikasi" name="nama_aplikasi" value="{{ $settings['nama_aplikasi'] ?? 'Kampung Paru' }}" maxlength="60" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
        <div><label for="lokasi_singkat" style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Lokasi Singkat</label><input type="text" id="lokasi_singkat" name="lokasi_singkat" value="{{ $settings['lokasi_singkat'] ?? 'Garut, Jawa Barat' }}" maxlength="100" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;" placeholder="Tampil di badge halaman login"></div>
        <div style="grid-column:span 2;"><label for="tagline_aplikasi" style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Tagline</label><textarea id="tagline_aplikasi" name="tagline_aplikasi" rows="2" maxlength="200" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; font-family:inherit; resize:vertical;" placeholder="Satu baris per kalimat">{{ $settings['tagline_aplikasi'] ?? "Portal warga Kampung Paru.\nData keluarga, iuran, dan surat dalam satu tempat." }}</textarea><div style="font-size:11px; color:var(--text3); margin-top:4px;">Setiap baris baru tampil sebagai baris terpisah di halaman login.</div></div>
    </div>
</div>

{{-- data-tab-panel dipakai showTab() untuk kartu kedua dan seterusnya dalam satu tab.
     Pencarian lewat id hanya menemukan satu kartu per tab. --}}
<div class="card" data-tab-panel="info" style="display:none;">
    <div class="card-header"><div class="card-title"><i class="fas fa-map-marker-alt" style="color:var(--biru);" aria-hidden="true"></i> Informasi RW</div></div>
    <div class="card-sub">Data administrasi RW, dipakai untuk kop surat</div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:16px;">
        <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Nama RW</label><input type="text" name="nama_rw" value="{{ $settings['nama_rw'] ?? 'RW 10' }}" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
        <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Kelurahan</label><input type="text" name="kelurahan" value="{{ $settings['kelurahan'] ?? 'Sukakarya' }}" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
        <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Kecamatan</label><input type="text" name="kecamatan" value="{{ $settings['kecamatan'] ?? 'Tarogong Kidul' }}" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
        <div><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Kabupaten</label><input type="text" name="kabupaten" value="{{ $settings['kabupaten'] ?? 'Garut' }}" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;"></div>
        <div style="grid-column:span 2;"><label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Ketua RW</label><input type="text" name="ketua_rw" value="{{ $settings['ketua_rw'] ?? '' }}" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;" placeholder="Nama ketua RW (untuk header surat)"></div>
    </div>
</div>

<!-- Tab WhatsApp API -->
<div class="card" id="panelWa" style="display:none;">
    <div class="card-header"><div class="card-title"><i class="fab fa-whatsapp" style="color:#25D366;"></i> WhatsApp API · MPWA</div></div>
    <div class="card-sub">Konfigurasi koneksi ke gateway <strong>mpwa.jabnet.id</strong> · isi API Key dan nomor pengirim dari akun MPWA Anda.</div>
    <div style="display:grid; gap:16px; margin-top:16px;">

        <!-- API Key -->
        <div>
            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">
                API Key <span style="color:var(--merah);">*</span>
            </label>
            <input type="text" id="mpwaApiKeyInput" name="mpwa_api_key"
                   value="{{ $settings['mpwa_api_key'] ?? '' }}"
                   placeholder="Masukkan API Key MPWA Anda"
                   style="width:100%; padding:10px 14px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; font-family:monospace; background:var(--abu);">
            <span style="font-size:11px; color:var(--text3); margin-top:4px; display:block;">
                Dapatkan API Key dari menu <strong>Setting</strong> di dashboard <a href="https://mpwa.jabnet.id" target="_blank" style="color:var(--hijau);">mpwa.jabnet.id</a>.
            </span>
        </div>

        <!-- Sender Number -->
        <div>
            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">
                Nomor Pengirim (Sender WA) <span style="color:var(--merah);">*</span>
            </label>
            <input type="text" id="mpwaSenderInput" name="mpwa_sender"
                   value="{{ $settings['mpwa_sender'] ?? '' }}"
                   placeholder="628xxxxxxxxxx"
                   style="width:100%; padding:10px 14px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px;">
            <span style="font-size:11px; color:var(--text3); margin-top:4px; display:block;">
                Nomor WhatsApp yang terdaftar sebagai <em>device</em> di akun MPWA. Format: 628xxxxx (tanpa + atau spasi).
            </span>
        </div>

        <!-- Status + Test Connection -->
        <div style="border-top:1px solid var(--abu2); padding-top:16px;">
            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:10px;">Status & Test Koneksi</label>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                @if(!empty($settings['mpwa_api_key']) && !empty($settings['mpwa_sender']))
                    <span id="mpwaStatusBadge" style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; background:var(--hijau-pale); color:var(--hijau); border-radius:6px; font-size:12px; font-weight:600;">
                        <i class="fas fa-circle" style="font-size:8px;"></i> Terkonfigurasi
                    </span>
                @else
                    <span id="mpwaStatusBadge" style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; background:var(--merah-pale); color:var(--merah); border-radius:6px; font-size:12px; font-weight:600;">
                        <i class="fas fa-circle" style="font-size:8px;"></i> Belum Dikonfigurasi
                    </span>
                @endif
            </div>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <input type="text" id="pgTestNumber"
                       placeholder="Nomor tujuan test WA (misal: 6281234567)"
                       style="flex:1; min-width:200px; padding:9px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:13px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="doMpwaTest()" id="mpwaTestBtn">
                    <i class="fas fa-wifi"></i> Kirim Test WA
                </button>
            </div>
            <div id="pgTestResult" style="display:none; margin-top:10px; padding:10px 14px; border-radius:var(--radius-sm); font-size:13px;"></div>
        </div>
    </div>

    <div style="margin-top:16px; padding-top:16px; border-top:1.5px solid var(--abu2);">
        <button type="submit" class="btn btn-primary" onclick="document.getElementById('activeTabInput').value='wa';"
                style="width:100%; padding:14px; font-size:15px; font-weight:700; background:#25D366; border-color:#25D366; cursor:pointer;">
            <i class="fas fa-save"></i> 💾 Simpan Konfigurasi WA
        </button>
        <p style="font-size:11px; color:var(--text3); text-align:center; margin-top:6px;">Klik tombol di atas untuk menyimpan API Key dan Nomor Pengirim</p>
    </div>
</div>



<!-- Tab Data -->
<div class="card" id="panelData" style="display:none;">
    <div class="card-header"><div class="card-title"><i class="fas fa-database" style="color:var(--hijau);"></i> Data & Backup</div></div>
    <div class="card-sub">Kelola data dan cadangan aplikasi</div>
    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:16px;">
        <button type="button" class="btn btn-outline" onclick="alert('Fitur backup akan segera hadir.')"><i class="fas fa-download"></i> Backup Data</button>
        <button type="button" class="btn btn-outline" onclick="alert('Fitur restore akan segera hadir.')"><i class="fas fa-upload"></i> Restore Data</button>
        <form method="POST" action="{{ route('pengaturan.removeDuplicates') }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-outline" style="color:var(--kuning, #e67e22); border-color:var(--kuning, #e67e22);" onclick="return confirm('Apakah Anda yakin ingin menghapus data duplikat?')"><i class="fas fa-broom"></i> Hapus Data Duplikat</button>
        </form>
        <button type="button" class="btn btn-outline" style="color:var(--merah); border-color:var(--merah);" onclick="showResetConfirm()"><i class="fas fa-exclamation-triangle"></i> Reset Semua Data</button>
    </div>
</div>

<div style="margin-top:16px;">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pengaturan</button>
</div>
</form>

<!-- SEC-01: Reset Double Confirmation Modal -->
<div id="resetModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width:90%; max-width:400px; margin:0;">
        <div class="card-header"><div class="card-title" style="color:var(--merah);"><i class="fas fa-exclamation-triangle"></i> PERINGATAN</div></div>
        <p style="font-size:13px; line-height:1.6; margin-bottom:16px;">Tindakan ini akan menghapus <strong>SELURUH DATA</strong> aplikasi termasuk data warga, transaksi, iuran, dan surat. Data yang sudah dihapus <strong>tidak dapat dikembalikan</strong>.</p>
        <div style="margin-bottom:16px;">
            <label style="display:block; font-size:12px; font-weight:700; color:var(--merah); margin-bottom:6px;">Ketik "RESET" untuk konfirmasi:</label>
            <input type="text" id="resetConfirmInput" placeholder="Ketik RESET" style="width:100%; padding:10px; border:2px solid var(--merah); border-radius:var(--radius-sm); font-size:14px; font-weight:700; text-transform:uppercase;">
        </div>
        <form method="POST" action="{{ route('pengaturan.reset') }}" style="display:flex; gap:10px; width:100%;">
            @csrf
            <input type="hidden" name="confirm" id="resetHiddenInput" value="">
            <button type="button" class="btn btn-outline" style="flex:1;" onclick="document.getElementById('resetModal').style.display='none'">Batal</button>
            <button type="submit" class="btn btn-primary" style="flex:1; background:var(--merah); border-color:var(--merah);" id="resetBtn" disabled>Hapus Semua Data</button>
        </form>
    </div>
</div>

<script>
function showTab(tab) {
    ['tarif','info','wa','data'].forEach(t => {
        const panelId = 'panel' + t.charAt(0).toUpperCase() + t.slice(1);
        const tabId = 'tab' + t.charAt(0).toUpperCase() + t.slice(1);
        const panel = document.getElementById(panelId);
        const tabEl = document.getElementById(tabId);
        if (panel) panel.style.display = t === tab ? '' : 'none';
        document.querySelectorAll('[data-tab-panel="' + t + '"]').forEach(el => {
            el.style.display = t === tab ? '' : 'none';
        });
        if (tabEl) tabEl.className = t === tab ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
    });
    const activeInput = document.getElementById('activeTabInput');
    if (activeInput) activeInput.value = tab;
}

// Auto-open tab: from ?tab= URL param (used after redirect from save)
const _qs = new URLSearchParams(window.location.search);
const _tab = _qs.get('tab') || 'tarif';
showTab(_tab);

@if(session('success'))
    // Show success toast
    const toast = document.createElement('div');
    toast.textContent = '✅ {{ session("success") }}';
    toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1a5c38;color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.25);';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
@endif


// SEC-01: Reset confirmation
function showResetConfirm() { document.getElementById('resetModal').style.display = 'flex'; }
document.getElementById('resetConfirmInput')?.addEventListener('input', function() {
    const isReset = this.value.trim().toUpperCase() === 'RESET';
    document.getElementById('resetBtn').disabled = !isReset;
    document.getElementById('resetHiddenInput').value = isReset ? 'RESET' : '';
});
document.getElementById('resetModal')?.addEventListener('click', function(e){if(e.target===this)this.style.display='none';});

// MPWA Test from Pengaturan
async function doMpwaTest() {
    const testNo  = document.getElementById('pgTestNumber').value.trim();
    const apiKey  = document.getElementById('mpwaApiKeyInput')?.value.trim() || '';
    const sender  = document.getElementById('mpwaSenderInput')?.value.trim() || '';
    const resultEl = document.getElementById('pgTestResult');
    const btn = document.getElementById('mpwaTestBtn');

    if (!apiKey)  return alert('Isi API Key MPWA terlebih dahulu.');
    if (!sender)  return alert('Isi Nomor Pengirim (Sender) terlebih dahulu.');
    if (!testNo)  return alert('Masukkan nomor WA tujuan test.');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
    resultEl.style.display = 'block';
    resultEl.style.background = 'var(--abu)';
    resultEl.style.color = 'var(--text2)';
    resultEl.textContent = '⏳ Menghubungi mpwa.jabnet.id...';

    try {
        const res = await fetch('{{ route("mpwa.test") }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({ api_key: apiKey, sender, test_number: testNo })
        });
        const data = await res.json();
        const ok = data.success;
        resultEl.style.background = ok ? 'var(--hijau-pale)' : 'var(--merah-pale)';
        resultEl.style.color      = ok ? 'var(--hijau)' : 'var(--merah)';
        resultEl.textContent = (ok ? '✅ ' : '❌ ') + (data.message || 'Selesai');

        // Update status badge
        const badge = document.getElementById('mpwaStatusBadge');
        if (badge) {
            badge.style.background = ok ? 'var(--hijau-pale)' : 'var(--merah-pale)';
            badge.style.color = ok ? 'var(--hijau)' : 'var(--merah)';
            badge.innerHTML = `<i class="fas fa-circle" style="font-size:8px;"></i> ${ok ? 'Terhubung ✓' : 'Koneksi Gagal'}`;
        }
    } catch(e) {
        resultEl.style.background = 'var(--merah-pale)';
        resultEl.style.color = 'var(--merah)';
        resultEl.textContent = '❌ Error: Tidak dapat menghubungi server.';
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-wifi"></i> Kirim Test WA';
}

</script>
@endsection
