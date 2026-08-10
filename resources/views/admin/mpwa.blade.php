@extends('layouts.app')
@section('title', 'MPWA Broadcast')
@section('page-title', 'MPWA Broadcast')
@section('page-subtitle', 'Kirim pesan WhatsApp ke warga')

@section('content')
{{-- ───────────── TOOLBAR ───────────── --}}
<div class="toolbar" style="flex-wrap:wrap;">
    <div class="toolbar-left" style="gap:6px;">
        <button class="btn btn-primary btn-sm" id="tabBroadcast" onclick="showMpwaTab('broadcast')"><i class="fab fa-whatsapp"></i> Broadcast</button>
        <button class="btn btn-outline btn-sm" id="tabTemplate" onclick="showMpwaTab('template')"><i class="fas fa-file-alt"></i> Template</button>
        <button class="btn btn-outline btn-sm" id="tabAturan" onclick="showMpwaTab('aturan')"><i class="fas fa-robot"></i> Aturan Otomatis</button>
    </div>
    <div class="toolbar-right">
        <a href="{{ route('pengaturan.index') }}?tab=wa" class="btn btn-outline btn-sm"><i class="fas fa-cog"></i> Setting WA</a>
    </div>
</div>

{{-- ───────────── STATS ───────────── --}}
<div class="stats-row" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card green"><div class="stat-accent"></div><div class="stat-icon-box" style="background:var(--hijau-pale);color:var(--hijau);"><i class="fab fa-whatsapp"></i></div><div class="stat-label">TEMPLATE</div><div class="stat-value" id="statTemplate">{{ count($templates) }}</div><div class="stat-sub">Tersedia</div></div>
    <div class="stat-card blue"><div class="stat-accent"></div><div class="stat-icon-box" style="background:var(--biru-pale);color:var(--biru);"><i class="fas fa-users"></i></div><div class="stat-label">TOTAL WARGA</div><div class="stat-value">{{ $wargaList->count() }}</div><div class="stat-sub">Punya nomor WA</div></div>
    <div class="stat-card gold"><div class="stat-accent"></div><div class="stat-icon-box" style="background:var(--emas-muda);color:var(--emas);"><i class="fas fa-paper-plane"></i></div><div class="stat-label">TOTAL TERKIRIM</div><div class="stat-value">0</div><div class="stat-sub">Hari ini</div></div>
    <div class="stat-card" style="border-left:4px solid var(--merah);"><div class="stat-accent" style="background:var(--merah);"></div><div class="stat-icon-box" style="background:var(--merah-pale);color:var(--merah);"><i class="fas fa-exclamation-circle"></i></div><div class="stat-label">WARGA MENUNGGAK</div><div class="stat-value">{{ $totalTunggakan }}</div><div class="stat-sub">Sampah: {{ $tunggakanSampah }}, Padaringan: {{ $tunggakanPadaringan }}</div></div>
</div>

{{-- ═══════════════════════════════════════ --}}
{{-- TAB 1: BROADCAST --}}
{{-- ═══════════════════════════════════════ --}}
<div id="panelBroadcast">
<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
    {{-- Compose --}}
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fab fa-whatsapp" style="color:#25D366;"></i> Tulis Pesan</div></div>
        <form id="broadcastForm">

            {{-- ── Target Penerima ── --}}
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Target Penerima</label>
                <select id="target" onchange="onTargetChange()" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">
                    <option value="semua">📣 Semua Warga ({{ $wargaList->count() }} orang)</option>
                    @for($i=1;$i<=6;$i++)<option value="rt{{ $i }}">👥 RT {{ str_pad($i,2,'0',STR_PAD_LEFT) }} saja</option>@endfor
                    <option value="tunggakan">⚠️ Warga Menunggak saja</option>
                    <option value="custom">👤 Pilih Manual / Perorangan</option>
                </select>
            </div>

            {{-- ── Custom Individual Picker ── --}}
            <div id="customRecipientBox" style="display:none; margin-bottom:14px;">
                <div style="font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Cari & Pilih Warga</div>
                <div style="position:relative; margin-bottom:8px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text3); font-size:13px;"></i>
                    <input type="text" id="wargaSearch" placeholder="Cari nama warga..." oninput="filterWarga()"
                           style="width:100%; padding:9px 12px 9px 32px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:13px; box-sizing:border-box;">
                </div>
                <div id="wargaCheckboxList" style="max-height:180px; overflow-y:auto; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); padding:8px; background:white; font-size:13px;">
                    @foreach($wargaList as $w)
                    <label class="warga-item" data-nama="{{ strtolower($w->kepala_keluarga ?? $w->nama ?? '') }}" data-rt="{{ $w->rt }}"
                           style="display:flex; align-items:center; gap:8px; padding:5px 4px; cursor:pointer; border-radius:4px; transition:background .15s;">
                        <input type="checkbox" class="warga-check" value="{{ $w->noHP }}"
                               data-nama="{{ $w->kepala_keluarga ?? $w->nama ?? '-' }}" data-rt="{{ $w->rt }}"
                               onchange="updateSelectedCount()">
                        <span>{{ $w->kepala_keluarga ?? $w->nama ?? '?' }}</span>
                        <span style="font-size:11px; color:var(--text3); margin-left:auto;">RT {{ $w->rt }}</span>
                    </label>
                    @endforeach
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px; font-size:12px; color:var(--text3);">
                    <span id="selectedCount">0 warga dipilih</span>
                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="selectAllVisible()" style="background:none; border:none; color:var(--hijau); font-size:12px; cursor:pointer; font-weight:600;">Pilih Semua</button>
                        <button type="button" onclick="clearAllSelected()" style="background:none; border:none; color:var(--merah); font-size:12px; cursor:pointer; font-weight:600;">Batal Pilih</button>
                    </div>
                </div>
            </div>

            {{-- ── Template ── --}}
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Template Pesan</label>
                <select id="templateSelect" onchange="applyTemplate()" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">
                    <option value="">✏️ Custom (Tulis sendiri)</option>
                    @foreach($templates as $tpl)
                    <option value="{{ $tpl['id'] }}">{{ $tpl['icon'] ?? '📝' }} {{ $tpl['nama'] }}</option>
                    @endforeach
                </select>
            </div>

            {{-- ── Pesan ── --}}
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Pesan
                    <span style="float:right; color:var(--text3); font-weight:400;" id="charCount">0 karakter</span>
                </label>
                <textarea id="pesanArea" rows="7" placeholder="Tulis pesan broadcast Anda di sini...&#10;&#10;Variabel: @{{nama}}, @{{rt}}, @{{tunggakan}}" oninput="updatePreview()" style="width:100%; padding:12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; font-family:inherit; resize:vertical; line-height:1.6;"></textarea>
                <div style="font-size:11px; color:var(--text3); margin-top:4px;">💡 Gunakan <code>@{{nama}}</code>, <code>@{{rt}}</code>, <code>@{{tunggakan}}</code> sebagai variabel dinamis</div>
            </div>

            {{-- ── Button Text (Opsional) ── --}}
            <div style="margin-bottom:14px;">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12px; font-weight:600; color:var(--text2);">
                        <input type="checkbox" id="enableButtons" onchange="toggleButtons()" style="accent-color:var(--hijau);">
                        Tambah Button Text
                    </label>
                    <span style="font-size:11px; color:var(--text3);">(max 3 tombol, cocok untuk polling/RSVP)</span>
                </div>
                <div id="buttonSection" style="display:none; padding:12px; background:var(--hijau-pale); border-radius:var(--radius-sm);">
                    <div id="buttonInputs" style="display:grid; gap:8px;"></div>
                    <button type="button" id="addBtnBtn" onclick="addButtonInput()" style="margin-top:8px; background:none; border:1.5px dashed var(--hijau); color:var(--hijau); border-radius:var(--radius-sm); padding:8px 14px; font-size:12px; font-weight:600; cursor:pointer; width:100%;">
                        <i class="fas fa-plus"></i> Tambah Tombol
                    </button>
                    <div style="font-size:11px; color:var(--text3); margin-top:6px;">💡 Contoh: "✅ Hadir", "❌ Tidak Hadir", "🤔 Belum Pasti"</div>
                </div>
            </div>

            {{-- ── Sender ── --}}
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--text2); margin-bottom:6px;">Nomor Pengirim (Sender WA)</label>
                <select id="senderSelect" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; background:white;">
                    @foreach($senders as $num => $label)
                        <option value="{{ $num }}" {{ $num == $savedSender ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- ── Actions ── --}}
            <div style="display:flex; gap:10px; margin-bottom:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="previewMsg()"><i class="fas fa-eye"></i> Preview</button>
                <button type="button" class="btn btn-primary" style="flex:1; background:#25D366; border-color:#25D366;" onclick="sendBroadcast()"><i class="fab fa-whatsapp"></i> Kirim Broadcast</button>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <input type="text" id="testNumber" placeholder="Nomor test WA (cth: 6281234567)" style="flex:1; padding:9px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:13px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="testConn()" style="white-space:nowrap;"><i class="fas fa-plug"></i> Test Koneksi</button>
            </div>

            {{-- Progress + Result --}}
            <div id="progressBox" style="display:none; margin-top:14px; background:var(--abu); border-radius:var(--radius-sm); padding:14px;">
                <div id="progressText" style="font-size:13px; font-weight:600; margin-bottom:8px;">Mengirim pesan...</div>
                <div style="background:var(--abu2); border-radius:6px; height:8px; overflow:hidden;">
                    <div id="progressBar" style="height:8px; background:var(--hijau); width:0%; transition:width 0.5s ease; border-radius:6px;"></div>
                </div>
            </div>
            <div id="resultBox" style="display:none; margin-top:10px; padding:12px; border-radius:var(--radius-sm);"></div>
        </form>
    </div>

    {{-- Live Preview --}}
    <div class="card" style="background:linear-gradient(135deg,#e8ded5 0%,#d9cfc5 100%); position:relative;">
        <div class="card-header" style="background:var(--hijau); color:white; margin:-20px -20px 16px; padding:14px 20px; border-radius:var(--radius) var(--radius) 0 0;">
            <div class="card-title" style="color:white;"><i class="fab fa-whatsapp"></i> Preview Pesan</div>
        </div>
        <div id="previewContainer" style="min-height:200px;">
            <div style="background:white; border-radius:0 12px 12px 12px; padding:12px 16px; max-width:85%; box-shadow:0 1px 2px rgba(0,0,0,.1); font-size:14px; line-height:1.6; margin-bottom:8px;">
                <div id="previewText" style="white-space:pre-wrap; color:var(--text);">Ketik pesan di kolom kiri untuk melihat preview...</div>
                <div id="previewFooter" style="font-size:11px; color:var(--text3); margin-top:4px; border-top:1px solid #e0e0e0; padding-top:4px; display:none;"></div>
                <div id="previewButtons" style="margin-top:8px; display:none;"></div>
                <div style="text-align:right; font-size:10px; color:var(--text3); margin-top:4px;">{{ date('H:i') }} ✓✓</div>
            </div>
        </div>
        <div style="margin-top:auto; display:flex; align-items:center; gap:8px; padding-top:12px;">
            <div style="flex:1; background:white; border-radius:20px; padding:8px 14px; font-size:13px; color:var(--text3);">Ketik pesan...</div>
            <div style="width:36px; height:36px; background:#25D366; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white;"><i class="fas fa-microphone"></i></div>
        </div>
    </div>
</div>
</div>{{-- /panelBroadcast --}}

{{-- ═══════════════════════════════════════ --}}
{{-- TAB 2: TEMPLATE --}}
{{-- ═══════════════════════════════════════ --}}
<div id="panelTemplate" style="display:none;">
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-file-alt" style="color:var(--biru);"></i> Manajemen Template Pesan</div>
        <button class="btn btn-primary btn-sm" onclick="showAddTemplate()"><i class="fas fa-plus"></i> Tambah Template</button>
    </div>
    <div class="card-sub">Template tersimpan dapat dipilih saat broadcast. Gunakan variabel <code>@{{nama}}</code>, <code>@{{rt}}</code>, <code>@{{tunggakan}}</code>.</div>

    {{-- Template List --}}
    <div id="templateList" style="margin-top:16px; display:grid; gap:12px;">
        @forelse($templates as $tpl)
        <div class="template-card" id="tpl-{{ $tpl['id'] }}" style="border:1.5px solid var(--abu2); border-radius:var(--radius-sm); padding:16px; position:relative;">
            <div style="display:flex; align-items:flex-start; gap:10px;">
                <div style="font-size:24px; line-height:1;">{{ $tpl['icon'] ?? '📝' }}</div>
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:700; font-size:15px; margin-bottom:4px;">{{ $tpl['nama'] }}</div>
                    <div style="font-size:12px; color:var(--text3); margin-bottom:8px;">{{ $tpl['deskripsi'] ?? '' }}</div>
                    <div style="font-size:12px; color:var(--text2); white-space:pre-wrap; max-height:80px; overflow:hidden; position:relative;">{{ $tpl['isi'] }}<div style="position:absolute;bottom:0;left:0;right:0;height:30px;background:linear-gradient(transparent,white);"></div></div>
                </div>
                <div style="display:flex; flex-direction:column; gap:6px; flex-shrink:0;">
                    <button onclick="useTemplate('{{ $tpl['id'] }}')" class="btn btn-outline btn-sm" style="font-size:11px;"><i class="fas fa-paper-plane"></i> Gunakan</button>
                    <button onclick="editTemplate('{{ $tpl['id'] }}')" class="btn btn-outline btn-sm" style="font-size:11px;"><i class="fas fa-edit"></i> Edit</button>
                    @if(!($tpl['builtin'] ?? false))
                    <button onclick="deleteTemplate('{{ $tpl['id'] }}')" class="btn btn-outline btn-sm" style="font-size:11px; color:var(--merah); border-color:var(--merah);"><i class="fas fa-trash"></i></button>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div style="text-align:center; padding:40px; color:var(--text3);">
            <i class="fas fa-file-alt" style="font-size:36px; margin-bottom:12px;"></i>
            <p>Belum ada template. Klik "Tambah Template" untuk membuat.</p>
        </div>
        @endforelse
    </div>
</div>

{{-- Add/Edit Template Modal --}}
<div id="templateModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width:90%; max-width:560px; margin:0; max-height:90vh; overflow-y:auto;">
        <div class="card-header">
            <div class="card-title" id="tplModalTitle"><i class="fas fa-plus"></i> Tambah Template</div>
            <button type="button" onclick="closeTemplateModal()" style="background:none; border:none; cursor:pointer; font-size:18px; color:var(--text3);">✕</button>
        </div>
        <input type="hidden" id="tplId" value="">
        <div style="display:grid; gap:14px; margin-top:16px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Nama Template *</label>
                    <input type="text" id="tplNama" placeholder="cth: Reminder Tunggakan" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Ikon Emoji</label>
                    <input type="text" id="tplIcon" placeholder="🔔" maxlength="4" style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:18px; text-align:center; box-sizing:border-box;">
                </div>
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Deskripsi Singkat</label>
                <input type="text" id="tplDeskripsi" placeholder="Keterangan singkat..." style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Isi Pesan *
                    <span style="float:right; font-weight:400; font-size:11px; color:var(--text3);">Variabel: @{{nama}} @{{rt}} @{{tunggakan}}</span>
                </label>
                <textarea id="tplIsi" rows="10" placeholder="Tulis isi template..." style="width:100%; padding:10px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:14px; font-family:inherit; resize:vertical; box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeTemplateModal()">Batal</button>
                <button type="button" class="btn btn-primary" onclick="saveTemplate()"><i class="fas fa-save"></i> Simpan Template</button>
            </div>
        </div>
    </div>
</div>
</div>{{-- /panelTemplate --}}

{{-- ═══════════════════════════════════════ --}}
{{-- TAB 3: ATURAN OTOMATIS --}}
{{-- ═══════════════════════════════════════ --}}
<div id="panelAturan" style="display:none;">
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-robot" style="color:var(--hijau);"></i> Aturan Broadcast Otomatis</div>
    </div>
    <div class="card-sub" style="margin-bottom:16px;">Aktifkan atau nonaktifkan notifikasi WhatsApp otomatis yang dikirim sistem saat event tertentu terjadi.</div>

    <form id="aturanForm" style="display:grid; gap:0;">

        @php
        $aturanList = [
            ['key'=>'notif_bayar_sampah',        'icon'=>'🗑️', 'judul'=>'Konfirmasi Pembayaran Iuran Sampah',    'sub'=>'Kirim bukti WA ke warga setiap kali pembayaran iuran sampah dicatat'],
            ['key'=>'notif_bayar_padaringan',     'icon'=>'🍳', 'judul'=>'Konfirmasi Pembayaran Iuran Padaringan','sub'=>'Kirim bukti WA ke warga setiap kali pembayaran iuran padaringan dicatat'],
            ['key'=>'notif_daftar_submitted',    'icon'=>'📝', 'judul'=>'Konfirmasi Pendaftaran Baru',           'sub'=>'Kirim WA ke calon warga setelah mereka mengirim formulir pendaftaran'],
            ['key'=>'notif_daftar_disetujui',    'icon'=>'✅', 'judul'=>'Pendaftaran Disetujui',                 'sub'=>'Kirim WA beserta info akun (username + PIN) saat admin approve pendaftaran'],
            ['key'=>'notif_daftar_ditolak',      'icon'=>'❌', 'judul'=>'Pendaftaran Ditolak',                  'sub'=>'Kirim WA dengan alasan penolakan saat admin menolak pendaftaran'],
            ['key'=>'notif_aduan_baru',          'icon'=>'📢', 'judul'=>'Aduan Baru dari Warga',                'sub'=>'Kirim WA ke semua pengurus (RT/RW/Admin) saat warga mengajukan aduan baru'],
            ['key'=>'notif_aduan_selesai',       'icon'=>'✅', 'judul'=>'Aduan Selesai Ditindaklanjuti',        'sub'=>'Kirim WA ke warga pelapor saat aduannya selesai ditangani'],
            ['key'=>'notif_surat_diajukan',      'icon'=>'📋', 'judul'=>'Surat Baru Perlu Tanda Tangan',       'sub'=>'Kirim WA ke petugas RT saat warga mengajukan surat baru'],
            ['key'=>'notif_surat_ttd_rw',        'icon'=>'✍️', 'judul'=>'Surat Perlu TTD Ketua RW',           'sub'=>'Kirim WA ke RW saat surat sudah ditandatangani RT'],
            ['key'=>'notif_surat_cap',           'icon'=>'🔏', 'judul'=>'Surat Perlu Cap Sekretaris',          'sub'=>'Kirim WA ke admin/sekretaris saat surat sudah TTD RW'],
            ['key'=>'notif_surat_selesai',       'icon'=>'✅', 'judul'=>'Surat Selesai Diproses',              'sub'=>'Kirim WA ke warga pemohon saat surat selesai (TTD + Cap)'],
        ];
        @endphp

        @foreach($aturanList as $i => $a)
        @php $isOn = ($settings[$a['key']] ?? '1') == '1'; @endphp
        <div style="display:flex; align-items:flex-start; gap:16px; padding:16px 4px; {{ $i < count($aturanList)-1 ? 'border-bottom:1px solid var(--abu2);' : '' }}">
            <div style="font-size:28px; flex-shrink:0; margin-top:2px;">{{ $a['icon'] }}</div>
            <div style="flex:1; min-width:0;">
                <div style="font-weight:600; font-size:14px; margin-bottom:3px;">{{ $a['judul'] }}</div>
                <div style="font-size:12px; color:var(--text3);">{{ $a['sub'] }}</div>
            </div>
            <div class="toggle-wrap" style="flex-shrink:0;">
                <label class="switch" style="position:relative; display:inline-block; width:46px; height:26px; cursor:pointer;">
                    <input type="checkbox" name="{{ $a['key'] }}" value="1" class="aturan-toggle" {{ $isOn ? 'checked' : '' }}
                           onchange="saveAturan('{{ $a['key'] }}', this.checked)"
                           style="opacity:0; width:0; height:0; position:absolute;">
                    <span class="slider" style="position:absolute; inset:0; background:{{ $isOn ? 'var(--hijau)' : 'var(--abu3)' }}; border-radius:26px; transition:.3s; cursor:pointer;">
                        <span style="position:absolute; height:20px; width:20px; left:{{ $isOn ? '23px' : '3px' }}; bottom:3px; background:white; border-radius:50%; transition:.3s; box-shadow:0 1px 3px rgba(0,0,0,.2);" class="slider-knob"></span>
                    </span>
                </label>
                <div style="font-size:11px; text-align:center; font-weight:600; color:{{ $isOn ? 'var(--hijau)' : 'var(--text3)' }}; margin-top:4px;" class="toggle-label" id="label-{{ $a['key'] }}">{{ $isOn ? 'AKTIF' : 'NONAKTIF' }}</div>
            </div>
        </div>
        @endforeach

        <div style="margin-top:20px; padding:14px; background:var(--abu); border-radius:var(--radius-sm); font-size:12px; color:var(--text3); line-height:1.6;">
            <i class="fas fa-info-circle" style="color:var(--biru);"></i>
            <strong>Catatan:</strong> Notifikasi otomatis menggunakan API Key dan Sender yang dikonfigurasi di
            <a href="{{ route('pengaturan.index') }}?tab=wa" style="color:var(--hijau); font-weight:600;">Pengaturan → WhatsApp API</a>.
            Jika API Key belum diisi, pesan tidak akan dikirim meski aturan diaktifkan.
        </div>
    </form>
</div>
</div>{{-- /panelAturan --}}

{{-- ═══════════════════════════════════════ --}}
{{-- JAVASCRIPT --}}
{{-- ═══════════════════════════════════════ --}}
<script>
const CSRF           = '{{ csrf_token() }}';
const ROUTE_BROADCAST = '{{ route("mpwa.broadcast") }}';
const ROUTE_TEST      = '{{ route("mpwa.test") }}';
const ROUTE_SAVE_ATURAN = '{{ route("mpwa.saveAturan") }}';
const ROUTE_SAVE_TEMPLATE = '{{ route("mpwa.saveTemplate") }}';
const ROUTE_DEL_TEMPLATE  = '{{ route("mpwa.deleteTemplate") }}';

// Raw templates from backend
let tplData = @json($templates);

// ── TAB NAVIGATION ──────────────────────────────────────────
function showMpwaTab(tab) {
    ['broadcast','template','aturan'].forEach(t => {
        document.getElementById('panel' + t.charAt(0).toUpperCase() + t.slice(1)).style.display = t === tab ? '' : 'none';
        document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1)).className = t === tab ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
    });
}

// ── TARGET CHANGE ──────────────────────────────────────────
function onTargetChange() {
    const v = document.getElementById('target').value;
    document.getElementById('customRecipientBox').style.display = v === 'custom' ? '' : 'none';
}

// ── WARGA SEARCH / FILTER ───────────────────────────────────
function filterWarga() {
    const q = document.getElementById('wargaSearch').value.toLowerCase();
    document.querySelectorAll('.warga-item').forEach(el => {
        el.style.display = el.dataset.nama.includes(q) ? '' : 'none';
    });
}
function updateSelectedCount() {
    const n = document.querySelectorAll('.warga-check:checked').length;
    document.getElementById('selectedCount').textContent = n + ' warga dipilih';
}
function selectAllVisible() {
    document.querySelectorAll('.warga-item').forEach(el => {
        if (el.style.display !== 'none') el.querySelector('input').checked = true;
    });
    updateSelectedCount();
}
function clearAllSelected() {
    document.querySelectorAll('.warga-check').forEach(i => i.checked = false);
    updateSelectedCount();
}

// ── TEMPLATE ─────────────────────────────────────────────────
function applyTemplate() {
    const v   = document.getElementById('templateSelect').value;
    const tpl = tplData.find(t => t.id == v);
    document.getElementById('pesanArea').value = tpl ? tpl.isi : '';
    updatePreview();
}
function useTemplate(id) {
    showMpwaTab('broadcast');
    document.getElementById('templateSelect').value = id;
    applyTemplate();
}
function updatePreview() {
    const text = document.getElementById('pesanArea').value;
    document.getElementById('charCount').textContent = text.length + ' karakter';
    const preview = text
        .replace(/\{\{nama\}\}/g,'Ahmad Suryadi')
        .replace(/\{\{rt\}\}/g,'03')
        .replace(/\{\{tunggakan\}\}/g,'150.000')
        || 'Ketik pesan di kolom kiri untuk melihat preview...';
    document.getElementById('previewText').textContent = preview;
}
function previewMsg() {
    if (!document.getElementById('pesanArea').value) return alert('Tulis pesan terlebih dahulu.');
    updatePreview();
}

// ── TEMPLATE MODAL ──────────────────────────────────────────
function showAddTemplate() {
    document.getElementById('tplModalTitle').innerHTML = '<i class="fas fa-plus"></i> Tambah Template';
    document.getElementById('tplId').value = '';
    document.getElementById('tplNama').value = '';
    document.getElementById('tplIcon').value = '📝';
    document.getElementById('tplDeskripsi').value = '';
    document.getElementById('tplIsi').value = '';
    document.getElementById('templateModal').style.display = 'flex';
}
function editTemplate(id) {
    const t = tplData.find(x => x.id == id);
    if (!t) return;
    document.getElementById('tplModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Template';
    document.getElementById('tplId').value = t.id;
    document.getElementById('tplNama').value = t.nama;
    document.getElementById('tplIcon').value = t.icon || '📝';
    document.getElementById('tplDeskripsi').value = t.deskripsi || '';
    document.getElementById('tplIsi').value = t.isi;
    document.getElementById('templateModal').style.display = 'flex';
}
function closeTemplateModal() {
    document.getElementById('templateModal').style.display = 'none';
}
document.getElementById('templateModal')?.addEventListener('click', function(e){ if(e.target===this) closeTemplateModal(); });

async function saveTemplate() {
    const nama = document.getElementById('tplNama').value.trim();
    const isi  = document.getElementById('tplIsi').value.trim();
    if (!nama) return alert('Nama template harus diisi.');
    if (!isi)  return alert('Isi pesan harus diisi.');

    const payload = {
        id:        document.getElementById('tplId').value || null,
        nama,
        icon:      document.getElementById('tplIcon').value || '📝',
        deskripsi: document.getElementById('tplDeskripsi').value.trim(),
        isi
    };

    const res = await fetch(ROUTE_SAVE_TEMPLATE, {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
        tplData = data.templates;
        refreshTemplateSelect(); refreshTemplateList(data.templates);
        document.getElementById('statTemplate').textContent = data.templates.length;
        closeTemplateModal();
        showToast('✅ Template berhasil disimpan!');
    } else {
        alert('Gagal: ' + (data.message || 'Error'));
    }
}

async function deleteTemplate(id) {
    if (!confirm('Hapus template ini?')) return;
    const res = await fetch(ROUTE_DEL_TEMPLATE, {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (data.success) {
        tplData = data.templates;
        refreshTemplateSelect(); refreshTemplateList(data.templates);
        document.getElementById('statTemplate').textContent = data.templates.length;
        showToast('🗑️ Template dihapus.');
    }
}

function refreshTemplateSelect() {
    const sel = document.getElementById('templateSelect');
    const cur = sel.value;
    while (sel.options.length > 1) sel.remove(1);
    tplData.forEach(t => {
        const o = new Option(`${t.icon||'📝'} ${t.nama}`, t.id);
        sel.add(o);
    });
    sel.value = cur;
}

function refreshTemplateList(tpls) {
    const list = document.getElementById('templateList');
    if (!tpls.length) { list.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text3);"><i class="fas fa-file-alt" style="font-size:36px;display:block;margin-bottom:12px;"></i>Belum ada template.</div>'; return; }
    list.innerHTML = tpls.map(t => `
        <div class="template-card" id="tpl-${t.id}" style="border:1.5px solid var(--abu2);border-radius:var(--radius-sm);padding:16px;position:relative;">
            <div style="display:flex;align-items:flex-start;gap:10px;">
                <div style="font-size:24px;line-height:1;">${t.icon||'📝'}</div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:15px;margin-bottom:4px;">${t.nama}</div>
                    <div style="font-size:12px;color:var(--text3);margin-bottom:6px;">${t.deskripsi||''}</div>
                    <div style="font-size:12px;white-space:pre-wrap;max-height:60px;overflow:hidden;color:var(--text2);">${t.isi.substring(0,150)}${t.isi.length>150?'...':''}</div>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
                    <button onclick="useTemplate('${t.id}')" class="btn btn-outline btn-sm" style="font-size:11px;"><i class="fas fa-paper-plane"></i> Gunakan</button>
                    <button onclick="editTemplate('${t.id}')" class="btn btn-outline btn-sm" style="font-size:11px;"><i class="fas fa-edit"></i> Edit</button>
                    ${!t.builtin ? `<button onclick="deleteTemplate('${t.id}')" class="btn btn-outline btn-sm" style="font-size:11px;color:var(--merah);border-color:var(--merah);"><i class="fas fa-trash"></i></button>` : ''}
                </div>
            </div>
        </div>
    `).join('');
}

// ── ATURAN OTOMATIS TOGGLE ────────────────────────────────────
async function saveAturan(key, isOn) {
    const label = document.getElementById('label-' + key);
    const toggle = event.target.nextElementSibling;
    const knob   = toggle?.querySelector('.slider-knob');

    toggle.style.background = isOn ? 'var(--hijau)' : 'var(--abu3)';
    if (knob) knob.style.left = isOn ? '23px' : '3px';
    if (label) { label.textContent = isOn ? 'AKTIF' : 'NONAKTIF'; label.style.color = isOn ? 'var(--hijau)' : 'var(--text3)'; }

    try {
        const res = await fetch(ROUTE_SAVE_ATURAN, {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body: JSON.stringify({ key, value: isOn ? '1' : '0' })
        });
        const data = await res.json();
        showToast(data.success ? (isOn ? '✅ Notifikasi diaktifkan' : '🔕 Notifikasi dinonaktifkan') : '❌ Gagal menyimpan');
    } catch(e) {
        showToast('❌ Koneksi error');
    }
}

// ── BROADCAST ─────────────────────────────────────────────────
async function testConn() {
    const sender = document.getElementById('senderSelect').value;
    const testNo = document.getElementById('testNumber').value.trim();
    if (!testNo) return alert('Masukkan nomor WhatsApp tujuan test.');
    const btn = event.target; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    const res = await fetch(ROUTE_TEST, {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body: JSON.stringify({ sender, test_number: testNo })
    });
    const data = await res.json();
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-plug"></i> Test Koneksi';
    showResult(data.success, data.message || (data.success ? 'Test berhasil!' : 'Test gagal.'));
}

async function sendBroadcast() {
    const pesan  = document.getElementById('pesanArea').value.trim();
    const target = document.getElementById('target').value;
    const sender = document.getElementById('senderSelect').value;
    let customNumbers = [];

    if (!pesan) return alert('Tulis pesan terlebih dahulu.');

    if (target === 'custom') {
        const checked = document.querySelectorAll('.warga-check:checked');
        customNumbers = Array.from(checked).map(c => c.value);
        if (!customNumbers.length) return alert('Pilih minimal satu warga untuk dikirim pesan.');
    }

    const tgtLabel = document.getElementById('target').options[document.getElementById('target').selectedIndex].text;
    if (!confirm(`Kirim broadcast ke:\n"${tgtLabel}"\n\nYakin?`)) return;

    document.getElementById('progressBox').style.display = 'block';
    document.getElementById('resultBox').style.display = 'none';
    document.getElementById('progressText').textContent = 'Mengirim pesan ke warga...';
    document.getElementById('progressBar').style.width = '30%';

    // Collect buttons
    const buttons = [];
    if (document.getElementById('enableButtons')?.checked) {
        document.querySelectorAll('.btn-text-input').forEach(inp => {
            const t = inp.value.trim();
            if (t) buttons.push(t);
        });
    }

    try {
        const res = await fetch(ROUTE_BROADCAST, {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body: JSON.stringify({ pesan, target, sender, custom_numbers: customNumbers, buttons })
        });
        document.getElementById('progressBar').style.width = '100%';
        const data = await res.json();
        setTimeout(() => {
            document.getElementById('progressBox').style.display = 'none';
            showResult(data.success, data.message, data);
        }, 500);
    } catch(e) {
        document.getElementById('progressBox').style.display = 'none';
        showResult(false, 'Gagal koneksi ke server: ' + e.message);
    }
}

function showResult(success, message, data) {
    const box = document.getElementById('resultBox');
    box.style.display = 'block';
    box.style.background = success ? 'var(--hijau-pale)' : 'var(--merah-pale)';
    box.style.border = `1px solid ${success ? 'var(--hijau)' : 'var(--merah)'}`;
    box.style.color  = success ? 'var(--hijau)' : 'var(--merah)';
    let html = `<div style="font-weight:700;margin-bottom:4px;"><i class="fas fa-${success?'check':'exclamation'}-circle"></i> ${message}</div>`;
    if (data?.sent !== undefined) {
        html += `<div style="font-size:12px;margin-top:6px;">✅ Terkirim: <strong>${data.sent}</strong> &nbsp;|&nbsp; ❌ Gagal: <strong>${data.failed}</strong> &nbsp;|&nbsp; Total: <strong>${data.total}</strong></div>`;
        if (data.errors?.length) html += `<div style="font-size:11px;margin-top:4px;opacity:.8;">Error: ${data.errors.join(', ')}</div>`;
    }
    box.innerHTML = html;
}

function showToast(msg) {
    const t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1a5c38;color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.25);';
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// ═══ BUTTON TEXT MANAGEMENT ═══
function toggleButtons() {
    const on = document.getElementById('enableButtons').checked;
    document.getElementById('buttonSection').style.display = on ? '' : 'none';
    if (on && document.getElementById('buttonInputs').children.length === 0) {
        addButtonInput();
    }
    updateButtonPreview();
}

function addButtonInput() {
    const container = document.getElementById('buttonInputs');
    if (container.children.length >= 3) return alert('Maksimal 3 tombol.');
    const idx = container.children.length + 1;
    const row = document.createElement('div');
    row.style.cssText = 'display:flex; align-items:center; gap:8px;';
    row.innerHTML = `
        <span style="font-size:12px; font-weight:700; color:var(--hijau); min-width:20px;">${idx}.</span>
        <input type="text" class="btn-text-input" maxlength="25" placeholder="Teks tombol (max 25 karakter)"
               oninput="updateButtonPreview()"
               style="flex:1; padding:8px 12px; border:1.5px solid var(--abu2); border-radius:var(--radius-sm); font-size:13px;">
        <button type="button" onclick="removeButtonInput(this)" style="background:none; border:none; color:var(--merah); cursor:pointer; font-size:16px; padding:4px;">
            <i class="fas fa-times-circle"></i>
        </button>`;
    container.appendChild(row);
    // Toggle add button visibility
    document.getElementById('addBtnBtn').style.display = container.children.length >= 3 ? 'none' : '';
}

function removeButtonInput(btn) {
    const row = btn.closest('div');
    row.remove();
    // Renumber
    const container = document.getElementById('buttonInputs');
    Array.from(container.children).forEach((r, i) => {
        r.querySelector('span').textContent = (i + 1) + '.';
    });
    document.getElementById('addBtnBtn').style.display = container.children.length >= 3 ? 'none' : '';
    if (container.children.length === 0) {
        document.getElementById('enableButtons').checked = false;
        document.getElementById('buttonSection').style.display = 'none';
    }
    updateButtonPreview();
}

function updateButtonPreview() {
    const previewBtns = document.getElementById('previewButtons');
    const previewFooter = document.getElementById('previewFooter');
    const on = document.getElementById('enableButtons')?.checked;
    if (!on) {
        previewBtns.style.display = 'none';
        previewFooter.style.display = 'none';
        return;
    }
    const inputs = document.querySelectorAll('.btn-text-input');
    let html = '';
    inputs.forEach(inp => {
        const txt = inp.value.trim() || 'Tombol';
        html += `<div style="border:1px solid #d4d4d4; border-radius:8px; padding:8px; text-align:center; font-size:13px; font-weight:600; color:#0d9488; cursor:pointer; margin-top:4px;">${txt}</div>`;
    });
    previewBtns.innerHTML = html;
    previewBtns.style.display = inputs.length ? '' : 'none';
    previewFooter.textContent = 'SukaWarga10 • RW 10 Sukakarya';
    previewFooter.style.display = inputs.length ? '' : 'none';
}
</script>
@endsection
