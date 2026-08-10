/* Pages — MPWA Broadcast Cerdas + Laporan & Analisis v4.0 */
/* Integrated with MPWA API: https://mpwa.jabnet.id */

// ===================================================================
// MPWA HELPERS
// ===================================================================
const MPWA = {
  // --- API Configuration ---
  get API_BASE() {
    return DB.getSetting('mpwaUrl', 'https://mpwa.jabnet.id') + '/public';
  },

  getConfig() {
    return {
      api_key: DB.getSetting('mpwaApiKey', ''),
      sender: DB.getSetting('mpwaSender', ''),
    };
  },

  isConfigured() {
    const c = this.getConfig();
    return !!(c.api_key && c.sender);
  },

  // --- Send Message via MPWA API ---
  async send(number, message, footer) {
    const cfg = this.getConfig();
    if (!cfg.api_key || !cfg.sender) {
      throw new Error('MPWA belum dikonfigurasi. Buka Pengaturan WA.');
    }
    const body = {
      api_key: cfg.api_key,
      sender: cfg.sender,
      number: this.formatWANum(number),
      message: message,
    };
    if (footer) body.footer = footer;

    const res = await fetch(`${this.API_BASE}/send-message`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    const json = await res.json();
    if (!json.status) throw new Error(json.msg || 'Gagal mengirim pesan');
    return json;
  },

  // --- Broadcast to multiple numbers with delay ---
  async broadcast(recipients, messageFn, { delayMs, onProgress, footerFn } = {}) {
    const actualDelay = delayMs || (parseInt(DB.getSetting('mpwaDelay', '8')) * 1000) || 8000;
    const results = [];
    for (let i = 0; i < recipients.length; i++) {
      const r = recipients[i];
      const num = this.formatWANum(r.noHP);
      if (!num) {
        results.push({ nama: r.nama, status: 'skip', error: 'No HP kosong' });
        if (onProgress) onProgress(i + 1, recipients.length, r.nama, 'skip');
        continue;
      }
      try {
        const msg = typeof messageFn === 'function' ? messageFn(r) : messageFn;
        const footer = typeof footerFn === 'function' ? footerFn(r) : (footerFn || '');
        await this.send(num, msg, footer);
        results.push({ nama: r.nama, status: 'sent' });
        if (onProgress) onProgress(i + 1, recipients.length, r.nama, 'sent');
      } catch (e) {
        results.push({ nama: r.nama, status: 'failed', error: e.message });
        if (onProgress) onProgress(i + 1, recipients.length, r.nama, 'failed');
      }
      if (i < recipients.length - 1) {
        await new Promise(resolve => setTimeout(resolve, actualDelay));
      }
    }
    return results;
  },

  // --- Test Connection ---
  async testConnection() {
    const cfg = this.getConfig();
    if (!cfg.api_key) throw new Error('API Key belum diisi');
    const res = await fetch(`${this.API_BASE}/info-user?api_key=${cfg.api_key}`);
    return await res.json();
  },

  // --- Check Device Status ---
  async checkDevice() {
    const cfg = this.getConfig();
    if (!cfg.api_key) throw new Error('API Key belum diisi');
    const res = await fetch(`${this.API_BASE}/info-devices?api_key=${cfg.api_key}`);
    return await res.json();
  },

  DEFAULT_TEMPLATES: [
    {
      id: 'tpl_reminder',
      nama: 'Reminder Iuran Bulanan',
      kategori: 'reminder',
      teks: `Assalamu'alaikum Wr Wb.\n\nYth. Bpk/Ibu *{{nama_warga}}* ({{rt}}),\n\nMohon informasi, iuran Padaringan bulan *{{iuran_bulan}} {{tahun}}* sebesar *Rp {{tarif_padaringan}}* belum tercatat di data kami.\n\nMohon segera konfirmasi ke pengurus RT atau Bendahara RW 10. Terima kasih atas kerjasamanya 🙏\n\n— *Pengurus RW 10 Kel. Sukakarya*`
    },
    {
      id: 'tpl_tunggakan',
      nama: 'Reminder Tunggakan Sampah',
      kategori: 'reminder',
      teks: `Assalamu'alaikum Wr Wb.\n\nYth. Bpk/Ibu *{{nama_warga}}* ({{rt}}),\n\nKami informasikan bahwa iuran sampah masih ada *{{tunggakan}}* minggu yang belum terbayar.\n\nTarif: Rp {{tarif_sampah}}/minggu. Mohon segera melunasi ke petugas RT setempat.\n\n— *Pengurus RW 10*`
    },
    {
      id: 'tpl_hari_raya',
      nama: 'Ucapan Hari Raya',
      kategori: 'event',
      teks: `Assalamu'alaikum Wr Wb.\n\nKeluarga besar Pengurus RW 10 Kel. Sukakarya mengucapkan:\n\n🌙 *Selamat Hari Raya Idul Fitri*\n*Minal Aidin wal Faizin*\nMohon maaf lahir & batin 🙏\n\nSemoga Allah SWT senantiasa memberikan keberkahan kepada kita semua.\n\n— *Pengurus RW 10*`
    },
    {
      id: 'tpl_pengumuman',
      nama: 'Pengumuman Kegiatan',
      kategori: 'pengumuman',
      teks: `Assalamu'alaikum Wr Wb.\n\nDengan hormat, mengajak seluruh warga RW 10 untuk menghadiri:\n\n📌 *Acara:* [nama acara]\n📅 *Hari/Tanggal:* [tanggal]\n🕐 *Waktu:* [waktu]\n📍 *Tempat:* [tempat]\n📌 *Agenda:* [agenda]\n\nMohon kehadirannya. Terima kasih 🙏\n\n— *Pengurus RW 10*`
    },
    {
      id: 'tpl_laporan_singkat',
      nama: 'Laporan Singkat Bulanan',
      kategori: 'laporan',
      teks: `📋 *LAPORAN SINGKAT RW 10*\n*{{iuran_bulan}} {{tahun}}*\n\n💰 Kas Sampah: Rp {{saldo_sampah}}\n🤝 Kas Padaringan: Rp {{saldo_padaringan}}\n\n📊 Kepatuhan Iuran:\n• Sampah: {{kepatuhan_sampah}}%\n• Padaringan: {{kepatuhan_padaringan}}%\n\nTerima kasih atas partisipasi seluruh warga 🙏\n\n— *Bendahara RW 10*`
    },
    {
      id: 'tpl_invoice_sampah',
      nama: 'INVOICE PAID - Iuran Sampah',
      kategori: 'invoice',
      teks: `✅ *KONFIRMASI PEMBAYARAN*\n*IURAN SAMPAH*\n\nYth. Bpk/Ibu *{{nama_warga}}* ({{rt}}),\n\nPembayaran iuran sampah Anda telah kami terima:\n\n📋 *Detail:*\n• Periode: *{{detail_periode}}*\n• Jumlah: *Rp {{detail_jumlah}}*\n• Tanggal Bayar: *{{detail_tanggal}}*\n• Status: ✅ *LUNAS*\n\nTerima kasih atas partisipasinya 🙏\n\n— *Bendahara RW 10 Kel. Sukakarya*`
    },
    {
      id: 'tpl_invoice_padaringan',
      nama: 'INVOICE PAID - Iuran Padaringan',
      kategori: 'invoice',
      teks: `✅ *KONFIRMASI PEMBAYARAN*\n*IURAN PADARINGAN*\n\nYth. Bpk/Ibu *{{nama_warga}}* ({{rt}}),\n\nPembayaran iuran padaringan Anda telah kami terima:\n\n📋 *Detail:*\n• Bulan: *{{detail_periode}}*\n• Nominal: *Rp {{detail_jumlah}}*\n• Tanggal Bayar: *{{detail_tanggal}}*\n• Status: ✅ *LUNAS*\n\nTerima kasih atas partisipasinya 🙏\n\n— *Bendahara RW 10 Kel. Sukakarya*`
    }
  ],

  getTemplates() {
    const saved = DB.getAll('mpwaTemplates');
    if (saved.length === 0) {
      this.DEFAULT_TEMPLATES.forEach(t => DB.insert('mpwaTemplates', t, true));
      return DB.getAll('mpwaTemplates');
    }
    return saved;
  },

  getTemplate(id) {
    return DB.getAll('mpwaTemplates').find(t => t.id === id);
  },

  resolveParams(teks, warga) {
    const tahun = DB.getSetting('tahun', 2026);
    const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
    const currentMonth = monthKeys[new Date().getMonth()];
    const stats = DB.getStats();
    const tarifSampah = DB.getSetting('tarifSampah', 25000);
    const tarifPadaringan = DB.getSetting('tarifPadaringan', 15000);

    let tunggakanMinggu = 0;
    if (warga) {
      const { keys: weekKeys } = DB.getWeeksInMonth(new Date().getMonth(), tahun);
      const samp = DB.getIuranSampah(warga.id, tahun);
      tunggakanMinggu = weekKeys.filter(wk => !(samp?.weeks?.[wk] > 0)).length;
    }

    return teks
      .replace(/{{nama_warga}}/g, warga?.nama || '[Nama Warga]')
      .replace(/{{rt}}/g, warga?.rt || '[RT]')
      .replace(/{{iuran_bulan}}/g, currentMonth)
      .replace(/{{tahun}}/g, tahun)
      .replace(/{{tunggakan}}/g, tunggakanMinggu)
      .replace(/{{tarif_sampah}}/g, tarifSampah.toLocaleString('id-ID'))
      .replace(/{{tarif_padaringan}}/g, tarifPadaringan.toLocaleString('id-ID'))
      .replace(/{{saldo_sampah}}/g, stats.saldoSampah.toLocaleString('id-ID'))
      .replace(/{{saldo_padaringan}}/g, stats.saldoPadaringan.toLocaleString('id-ID'))
      .replace(/{{kepatuhan_sampah}}/g, stats.belumBayarSampah ? Math.round((1 - stats.belumBayarSampah / Math.max(1, stats.ikutSampah)) * 100) : 0)
      .replace(/{{kepatuhan_padaringan}}/g, stats.belumBayarPadaringan ? Math.round((1 - stats.belumBayarPadaringan / Math.max(1, stats.ikutPadaringan)) * 100) : 0);
  },

  saveLog(data) {
    DB.insert('mpwaLog', {
      tanggal: new Date().toISOString(),
      template: data.template || '',
      penerima: data.penerima || 'Semua',
      noHP: data.noHP || '',
      jumlah: data.jumlah || 1,
      pesan: data.pesan || '',
      status: data.status || 'dikirim',
      device: data.device || DB.getSetting('mpwaDeviceName', ''),
      tipe: data.tipe || 'manual',
      error: data.error || '',
      detail: data.detail || null
    }, true);
  },

  formatWANum(hp) {
    if (!hp) return '';
    return hp.replace(/^0/, '62').replace(/\D/g, '');
  },

  // --- Auto-broadcast on payment ---
  async sendPaymentNotif(type, kkId, detail) {
    const autoBroadcast = DB.getSetting('mpwaAutoBroadcast', false);
    if (!autoBroadcast) return null;
    if (!this.isConfigured()) return null;

    const kk = DB.getById('keluarga', kkId);
    if (!kk?.noHP) return null;

    const tplId = type === 'sampah' ? 'tpl_invoice_sampah' : 'tpl_invoice_padaringan';
    const customTplId = DB.getSetting(type === 'sampah' ? 'mpwaTplSampah' : 'mpwaTplPadaringan', tplId);
    const tpl = this.getTemplates().find(t => t.id === customTplId) || this.getTemplates().find(t => t.id === tplId);
    if (!tpl) return null;

    let pesan = this.resolveParams(tpl.teks, kk);
    // Replace detail placeholders
    pesan = pesan
      .replace(/{{detail_periode}}/g, detail.periode || '-')
      .replace(/{{detail_jumlah}}/g, (detail.jumlah || 0).toLocaleString('id-ID'))
      .replace(/{{detail_tanggal}}/g, detail.tanggal || new Date().toISOString().slice(0, 10));

    const deviceName = DB.getSetting('mpwaDeviceName', 'SukaWarga10');

    try {
      await this.send(kk.noHP, pesan, 'SukaWarga10 · RW 10');
      this.saveLog({
        template: tpl.nama,
        penerima: kk.nama,
        noHP: kk.noHP,
        jumlah: 1,
        pesan: pesan.substring(0, 200),
        status: 'dikirim',
        device: deviceName,
        tipe: 'auto'
      });
      return { status: 'sent', nama: kk.nama };
    } catch (e) {
      this.saveLog({
        template: tpl.nama,
        penerima: kk.nama,
        noHP: kk.noHP,
        jumlah: 1,
        pesan: pesan.substring(0, 200),
        status: 'gagal',
        error: e.message,
        device: deviceName,
        tipe: 'auto'
      });
      return { status: 'failed', nama: kk.nama, error: e.message };
    }
  }
};

// ===================================================================
// Pages.mpwa — Main MPWA Page
// ===================================================================
Pages.mpwa = function () {
  const keluarga = DB.getAll('keluarga').filter(k => k.status === 'aktif' || !k.status);
  const tahun = DB.getSetting('tahun', 2026);
  const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
  const currentMonth = monthKeys[new Date().getMonth()];
  const tarifPadaringan = DB.getSetting('tarifPadaringan', 15000);
  const noWA = DB.getSetting('mpwaNoWA', '');

  // Warga dengan tunggakan padaringan bulan ini
  const tunggakanPadaringan = keluarga.filter(kk => {
    const pad = DB.getIuranPadaringan(kk.id, tahun);
    return !(pad?.months?.[currentMonth] > 0);
  });

  const templates = MPWA.getTemplates();
  const allLogs = DB.getAll('mpwaLog').reverse();
  const KATEGORI_ICONS = { reminder: '🔔', event: '🎉', pengumuman: '📣', laporan: '📋', invoice: '✅', custom: '✏️' };
  const apiOk = MPWA.isConfigured();

  // Auto-render log table after page load
  setTimeout(() => Pages._renderLogTable(), 100);

  return `
  <!-- API Status Banner -->
  ${!apiOk ? `<div style="background:var(--kuning-bg,#fff8e1);border:1px solid var(--kuning,#ffb300);border-radius:8px;padding:10px 16px;margin-bottom:12px;display:flex;align-items:center;gap:10px;font-size:.85rem">
    <span>⚠️</span>
    <span><strong>MPWA belum dikonfigurasi.</strong> Masukkan API Key dan nomor pengirim di <a href="#" onclick="Pages._showWASetup();return false" style="color:var(--primary);font-weight:600">Pengaturan WA</a> agar bisa mengirim langsung via API.</span>
  </div>` : ''}
  <!-- Toolbar -->
  <div class="toolbar">
    <div class="toolbar-left" style="flex-wrap:wrap;gap:8px">
      <button class="btn btn-warning" onclick="Pages._showReminderPanel()">
        🔔 Reminder Tunggakan (${tunggakanPadaringan.length})
      </button>
      <span class="text-muted" style="font-size:.82rem;align-self:center">${tunggakanPadaringan.length} warga belum bayar Padaringan ${currentMonth}</span>
    </div>
    <div class="toolbar-right" style="display:flex;gap:8px;flex-wrap:wrap">
      ${apiOk ? '<span class="badge badge-success" style="align-self:center">🟢 API Connected</span>' : ''}
      <button class="btn" onclick="Pages._showTemplateManager()">📝 Template</button>
      <button class="btn btn-primary" onclick="Pages._showWASetup()">⚙️ Pengaturan WA</button>
    </div>
  </div>

  <!-- Tab Bar -->
  <div class="tab-bar" style="margin-bottom:16px">
    <button class="tab-btn active" onclick="Pages._mpwaTab(this,'tabBroadcast')">📢 Broadcast</button>
    <button class="tab-btn" onclick="Pages._mpwaTab(this,'tabReminder')">🔔 Reminder</button>
    <button class="tab-btn" onclick="Pages._mpwaTab(this,'tabLog')">📋 Log Broadcast</button>
  </div>

  <!-- Tab: Broadcast -->
  <div id="tabBroadcast" class="tab-content active">
    <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:20px;align-items:start">
      <div class="glass-card">
        <div class="card-header"><span class="card-title">📢 Kirim Broadcast</span></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label" style="font-weight:600;color:var(--text);margin-bottom:6px">Template Pesan</label>
            <select class="form-control" id="mpwaTplSelect" onchange="Pages._onTplSelect()" style="padding:10px">
              <option value="">— Pilih Template —</option>
              ${templates.map(t => `<option value="${t.id}">${KATEGORI_ICONS[t.kategori] || '✏️'} ${t.nama}</option>`).join('')}
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" style="font-weight:600;color:var(--text);margin-bottom:6px">Pesan</label>
            <textarea class="form-control" id="mpwaMsg" rows="10" placeholder="Tulis atau pilih template di atas..." oninput="Pages._updateMPWAPreview()" style="resize:vertical;font-family:inherit;padding:12px;line-height:1.5"></textarea>
          </div>
          <div class="form-row form-row-2">
            <div class="form-group">
              <label class="form-label" style="font-weight:600;color:var(--text);margin-bottom:6px">Penerima</label>
              <select class="form-control" id="mpwaTarget" style="padding:10px">
                <option value="all">Semua Warga (${keluarga.length})</option>
                <option value="RT 01">RT 01 (${keluarga.filter(k => k.rt === 'RT 01').length} KK)</option>
                <option value="RT 02">RT 02 (${keluarga.filter(k => k.rt === 'RT 02').length} KK)</option>
                <option value="RT 03">RT 03 (${keluarga.filter(k => k.rt === 'RT 03').length} KK)</option>
                <option value="tunggakan">Tunggakan (${tunggakanPadaringan.length})</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" style="font-weight:600;color:var(--text);margin-bottom:6px">No. WA Pengirim (Opsional)</label>
              <input type="tel" class="form-control" id="mpwaFrom" value="${noWA}" placeholder="08xxxxxxxxxx" oninput="DB.setSetting('mpwaNoWA', this.value)" style="padding:10px">
            </div>
          </div>
          <div style="display:flex;gap:8px;margin-top:12px">
            <button class="btn btn-success" onclick="Pages.sendMPWA()" style="flex:1;padding:12px;font-size:14px;font-weight:600">
              📡 Kirim via MPWA API
            </button>
            <button class="btn btn-outline" onclick="Pages._sendViaWAMe()" title="Kirim manual via wa.me" style="padding:12px">
              📱 wa.me
            </button>
          </div>
          <div id="mpwaSendProgress" style="margin-top:10px;display:none"></div>
        </div>
      </div>

      <div class="glass-card" style="position:sticky;top:80px">
        <div class="card-header"><span class="card-title">📱 Preview WhatsApp</span></div>
        <div class="card-body" style="background:var(--bg2)">
          <div class="wa-preview" id="waPreview">
            <div class="wa-bubble"><span class="text-muted">Tulis pesan untuk melihat preview...</span></div>
          </div>
          <div style="margin-top:16px;padding:12px;background:white;border-radius:8px;border:1px solid var(--border);font-size:.78rem;color:var(--text-tertiary)">
            <strong style="display:block;margin-bottom:6px;color:var(--text2)">💡 Parameter yang tersedia:</strong>
            <div style="display:flex;flex-wrap:wrap;gap:4px">
              <code>{{nama_warga}}</code> <code>{{rt}}</code> <code>{{iuran_bulan}}</code> 
              <code>{{tahun}}</code> <code>{{tunggakan}}</code>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tab: Reminder -->
  <div id="tabReminder" class="tab-content">
    <div class="glass-card">
      <div class="card-header">
        <span class="card-title">🔔 Reminder Personal Tunggakan Padaringan — ${currentMonth} ${tahun}</span>
        <button class="btn btn-sm btn-success" onclick="Pages._sendAllReminder()">📱 Kirim Semua</button>
      </div>
      <div class="card-body">
        <div class="data-table-wrapper">
          <table class="data-table">
            <thead><tr><th>No</th><th>Nama</th><th>RT</th><th>No. HP</th><th>Preview Pesan</th><th>Aksi</th></tr></thead>
            <tbody>
            ${tunggakanPadaringan.length ? tunggakanPadaringan.map((kk, i) => {
    const tpl = MPWA.getTemplates().find(t => t.id === 'tpl_reminder') || MPWA.DEFAULT_TEMPLATES[0];
    const pesan = MPWA.resolveParams(tpl?.teks || '', kk);
    const waNum = MPWA.formatWANum(kk.noHP);
    const waLink = waNum ? `https://wa.me/${waNum}?text=${encodeURIComponent(pesan)}` : '';
    return `<tr>
                  <td>${i + 1}</td>
                  <td style="font-weight:600">${kk.nama}</td>
                  <td><span class="badge badge-info">${kk.rt}</span></td>
                  <td style="font-size:.82rem">${kk.noHP || '<span class="text-muted">—</span>'}</td>
                  <td style="font-size:.78rem;max-width:220px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis" title="${pesan.replace(/"/g, '&quot;')}">${pesan.replace(/\n/g, ' ')}</td>
                  <td style="white-space:nowrap">${waLink
        ? `<button class="btn btn-sm btn-success" onclick="Pages._sendSingleReminder('${kk.id}',this)" title="Kirim via API">📡 Kirim</button>
           <a href="${waLink}" target="_blank" class="btn btn-sm btn-outline" onclick="Pages._logReminder('${kk.id}')" title="Via wa.me">📱</a>`
        : '<span class="text-muted badge">No HP</span>'
      }</td>
                </tr>`;
  }).join('') : '<tr><td colspan="6" class="text-center text-muted">Semua warga sudah bayar Padaringan bulan ini! 🎉</td></tr>'}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Tab: Log -->
  <div id="tabLog" class="tab-content">
    <div class="glass-card">
      <div class="card-header">
        <span class="card-title">📋 List Broadcast</span>
        <div style="display:flex;gap:6px">
          <button class="btn btn-sm btn-outline" onclick="Pages._exportLogCSV()">📥 Export</button>
          <button class="btn btn-sm btn-danger" onclick="if(confirm('Hapus semua log broadcast?')){DB._saveAll('mpwaLog',[]);App.render('mpwa')}">🗑️ Hapus</button>
        </div>
      </div>
      <div class="card-body">
        <!-- Filters -->
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:center">
          <select class="filter-select" id="logFilterStatus" onchange="Pages._renderLogTable()" style="min-width:120px">
            <option value="">Semua Status</option>
            <option value="dikirim">Terkirim</option>
            <option value="gagal">Gagal</option>
            <option value="manual">Manual</option>
          </select>
          <select class="filter-select" id="logFilterTemplate" onchange="Pages._renderLogTable()" style="min-width:140px">
            <option value="">Semua Template</option>
            ${[...new Set(allLogs.map(l => l.template))].filter(Boolean).map(t => `<option value="${t}">${t}</option>`).join('')}
          </select>
          <input type="text" class="form-input" id="logFilterSearch" placeholder="Cari Pelanggan.." oninput="Pages._renderLogTable()" style="max-width:200px;padding:6px 10px;font-size:.82rem">
          <span style="font-size:.78rem;color:var(--text3);margin-left:auto">${allLogs.length} total record</span>
        </div>
        <div id="logTableContainer"></div>
      </div>
    </div>
  </div>`;
};

// --- Log table rendering with pagination ---
Pages._logPage = 1;
Pages._logPerPage = 20;
Pages._renderLogTable = function () {
  const allLogs = DB.getAll('mpwaLog').reverse();
  const status = document.getElementById('logFilterStatus')?.value || '';
  const tpl = document.getElementById('logFilterTemplate')?.value || '';
  const search = (document.getElementById('logFilterSearch')?.value || '').toLowerCase();

  let filtered = allLogs;
  if (status) filtered = filtered.filter(l => l.status === status);
  if (tpl) filtered = filtered.filter(l => l.template === tpl);
  if (search) filtered = filtered.filter(l => (l.penerima || '').toLowerCase().includes(search) || (l.noHP || '').includes(search));

  const totalPages = Math.max(1, Math.ceil(filtered.length / Pages._logPerPage));
  if (Pages._logPage > totalPages) Pages._logPage = totalPages;
  const start = (Pages._logPage - 1) * Pages._logPerPage;
  const pageItems = filtered.slice(start, start + Pages._logPerPage);
  const deviceName = DB.getSetting('mpwaDeviceName', 'SukaWarga10');

  const container = document.getElementById('logTableContainer');
  if (!container) return;

  if (!filtered.length) {
    container.innerHTML = '<div class="empty-state" style="padding:24px"><div class="empty-state-icon">📭</div><div class="empty-state-title">Belum ada log broadcast</div></div>';
    return;
  }

  const paginationHtml = totalPages > 1 ? `
    <div style="display:flex;align-items:center;justify-content:flex-end;gap:4px;margin-top:10px;font-size:.82rem">
      <span style="color:var(--text3);margin-right:8px">${start + 1}-${Math.min(start + Pages._logPerPage, filtered.length)}/${filtered.length}</span>
      <button class="btn btn-sm btn-outline" onclick="Pages._logPage=1;Pages._renderLogTable()" ${Pages._logPage <= 1 ? 'disabled' : ''}>&laquo;</button>
      <button class="btn btn-sm btn-outline" onclick="Pages._logPage--;Pages._renderLogTable()" ${Pages._logPage <= 1 ? 'disabled' : ''}>&lsaquo;</button>
      ${Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
    let p = Pages._logPage <= 3 ? i + 1 : Pages._logPage + i - 2;
    if (p > totalPages) p = totalPages - (4 - i);
    if (p < 1) p = i + 1;
    return `<button class="btn btn-sm ${p === Pages._logPage ? 'btn-primary' : 'btn-outline'}" onclick="Pages._logPage=${p};Pages._renderLogTable()">${p}</button>`;
  }).join('')}
      ${totalPages > 5 ? `<span>…</span><button class="btn btn-sm btn-outline" onclick="Pages._logPage=${totalPages};Pages._renderLogTable()">${totalPages}</button>` : ''}
      <button class="btn btn-sm btn-outline" onclick="Pages._logPage++;Pages._renderLogTable()" ${Pages._logPage >= totalPages ? 'disabled' : ''}>&rsaquo;</button>
    </div>` : '';

  container.innerHTML = `
    <div class="data-table-wrapper">
      <table class="data-table">
        <thead><tr>
          <th>No</th><th>Pelanggan</th><th>No Telepon</th><th>Tanggal</th><th>Perangkat</th><th>Template</th><th>Status</th><th>Aksi</th>
        </tr></thead>
        <tbody>
        ${pageItems.map((l, i) => {
    const statusBadge = l.status === 'dikirim' ? 'badge-success' : l.status === 'gagal' ? 'badge-danger' : 'badge-warning';
    const statusText = l.status === 'dikirim' ? 'Terkirim' : l.status === 'gagal' ? 'Gagal' : l.status === 'manual' ? 'Manual' : l.status;
    const dt = new Date(l.tanggal);
    const tglStr = dt.toLocaleDateString('id-ID') + ' ' + dt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    return `<tr>
            <td>${start + i + 1}</td>
            <td style="font-weight:600;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${l.penerima || '—'}</td>
            <td style="font-size:.82rem">${l.noHP || '—'}</td>
            <td style="font-size:.78rem;white-space:nowrap">${tglStr}<br><span style="color:var(--text3);font-size:.7rem">Oleh: System</span></td>
            <td style="font-size:.82rem">${l.device || deviceName || '—'}</td>
            <td><span class="badge badge-info" style="font-size:.72rem">${l.template || '—'}</span></td>
            <td><span class="badge ${statusBadge}">${statusText}</span></td>
            <td><button class="btn btn-sm btn-primary" onclick="Pages._showLogDetail('${l.id}')">Detail</button></td>
          </tr>`;
  }).join('')}
        </tbody>
      </table>
    </div>
    ${paginationHtml}`;
};

// --- Log detail modal ---
Pages._showLogDetail = function (logId) {
  const log = DB.getById('mpwaLog', logId) || DB.getAll('mpwaLog').find(l => l.id === logId);
  if (!log) { App.toast('Log tidak ditemukan', 'error'); return; }
  const dt = new Date(log.tanggal);
  const statusBadge = log.status === 'dikirim' ? 'badge-success' : log.status === 'gagal' ? 'badge-danger' : 'badge-warning';
  const statusText = log.status === 'dikirim' ? 'Terkirim' : log.status === 'gagal' ? 'Gagal' : log.status;

  App.showModal('📋 Detail Broadcast', `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
      <div style="background:var(--bg2);padding:10px;border-radius:8px">
        <div style="font-size:.75rem;color:var(--text3)">Pelanggan</div>
        <div style="font-weight:700;font-size:.95rem">${log.penerima || '—'}</div>
      </div>
      <div style="background:var(--bg2);padding:10px;border-radius:8px">
        <div style="font-size:.75rem;color:var(--text3)">No Telepon</div>
        <div style="font-weight:600;font-size:.95rem;font-family:'Space Mono',monospace">${log.noHP || '—'}</div>
      </div>
      <div style="background:var(--bg2);padding:10px;border-radius:8px">
        <div style="font-size:.75rem;color:var(--text3)">Tanggal</div>
        <div style="font-weight:600">${dt.toLocaleString('id-ID')}</div>
      </div>
      <div style="background:var(--bg2);padding:10px;border-radius:8px">
        <div style="font-size:.75rem;color:var(--text3)">Status</div>
        <div><span class="badge ${statusBadge}">${statusText}</span> ${log.tipe === 'auto' ? '<span class="badge badge-info" style="margin-left:4px">Auto</span>' : ''}</div>
      </div>
      <div style="background:var(--bg2);padding:10px;border-radius:8px">
        <div style="font-size:.75rem;color:var(--text3)">Perangkat</div>
        <div style="font-weight:600">${log.device || '—'}</div>
      </div>
      <div style="background:var(--bg2);padding:10px;border-radius:8px">
        <div style="font-size:.75rem;color:var(--text3)">Template</div>
        <div><span class="badge badge-info">${log.template || '—'}</span></div>
      </div>
    </div>
    ${log.error ? `<div style="background:#fee2e2;border:1px solid #fca5a5;padding:10px;border-radius:8px;margin-bottom:12px;font-size:.82rem">
      <strong>❌ Error:</strong> ${log.error}
    </div>` : ''}
    <div style="background:var(--bg2);border-radius:8px;padding:12px;margin-bottom:12px">
      <div style="font-size:.75rem;color:var(--text3);margin-bottom:6px;font-weight:600">Preview Pesan:</div>
      <div class="wa-preview"><div class="wa-bubble" style="font-size:.85rem;line-height:1.5;white-space:pre-wrap">${(log.pesan || '—').replace(/\n/g, '<br>').replace(/\*(.*?)\*/g, '<strong>$1</strong>')}</div></div>
    </div>
    <button class="btn btn-block" onclick="App.closeModal()">Tutup</button>
  `);
};

// --- Export log to CSV ---
Pages._exportLogCSV = function () {
  const allLogs = DB.getAll('mpwaLog');
  if (!allLogs.length) { App.toast('Tidak ada data log', 'error'); return; }
  const rows = allLogs.map(l => ({
    Tanggal: new Date(l.tanggal).toLocaleString('id-ID'),
    Pelanggan: l.penerima || '',
    NoTelepon: l.noHP || '',
    Template: l.template || '',
    Perangkat: l.device || '',
    Status: l.status || '',
    Tipe: l.tipe || '',
    Error: l.error || ''
  }));
  DB.exportCSV(rows, `log_broadcast_${new Date().toISOString().slice(0, 10)}.csv`);
  App.toast('📥 Log broadcast di-export!', 'success');
};

// ===================================================================
// MPWA Helpers
// ===================================================================
Pages._mpwaTab = function (btn, tabId) {
  document.querySelectorAll('#tabBroadcast,#tabReminder,#tabLog').forEach(t => t.classList.remove('active'));
  btn.closest('.tab-bar')?.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(tabId)?.classList.add('active');
};

Pages._onTplSelect = function () {
  const id = document.getElementById('mpwaTplSelect')?.value;
  if (!id) return;
  const tpl = MPWA.getTemplates().find(t => t.id === id);
  if (!tpl) return;
  const resolved = MPWA.resolveParams(tpl.teks, null);
  document.getElementById('mpwaMsg').value = resolved;
  Pages._updateMPWAPreview();
};

Pages._updateMPWAPreview = function () {
  const msg = document.getElementById('mpwaMsg')?.value || '';
  const preview = document.getElementById('waPreview');
  if (preview) {
    const html = msg
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/\n/g, '<br>')
      .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
      .replace(/_(.*?)_/g, '<em>$1</em>');
    preview.innerHTML = `<div class="wa-bubble">${html || '<span class="text-muted">Preview kosong</span>'}</div>`;
  }
};

// === Send via MPWA API (real broadcast) ===
Pages.sendMPWA = async function () {
  const msg = document.getElementById('mpwaMsg')?.value?.trim();
  if (!msg) { App.toast('Tulis pesan terlebih dahulu!', 'error'); return; }
  if (!MPWA.isConfigured()) { App.toast('⚠️ MPWA belum dikonfigurasi. Buka Pengaturan WA.', 'error'); Pages._showWASetup(); return; }
  const target = document.getElementById('mpwaTarget')?.value || 'all';
  const tplId = document.getElementById('mpwaTplSelect')?.value || '';
  const tpl = MPWA.getTemplates().find(t => t.id === tplId);

  // Get recipients
  const keluarga = DB.getAll('keluarga').filter(k => k.status === 'aktif' || !k.status);
  const tahun = DB.getSetting('tahun', 2026);
  const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
  const currentMonth = monthKeys[new Date().getMonth()];
  let recipients;
  if (target === 'all') recipients = keluarga;
  else if (target === 'tunggakan') recipients = keluarga.filter(kk => { const pad = DB.getIuranPadaringan(kk.id, tahun); return !(pad?.months?.[currentMonth] > 0); });
  else recipients = keluarga.filter(k => k.rt === target);

  const withHP = recipients.filter(r => MPWA.formatWANum(r.noHP));
  if (withHP.length === 0) { App.toast('⚠️ Tidak ada penerima dengan No. HP', 'error'); return; }

  if (!confirm(`Kirim pesan ke ${withHP.length} warga via MPWA API?\n\n(${recipients.length - withHP.length} warga tanpa No. HP akan dilewati)`)) return;

  // Show progress
  const prog = document.getElementById('mpwaSendProgress');
  if (prog) { prog.style.display = 'block'; prog.innerHTML = '<div style="text-align:center;padding:8px"><span class="spinner"></span> Memulai broadcast...</div>'; }

  const results = await MPWA.broadcast(
    withHP,
    (r) => MPWA.resolveParams(msg, r),
    {
      delayMs: 2000,
      footerFn: () => 'SukaWarga10 · RW 10 Sukakarya',
      onProgress: (i, total, nama, status) => {
        if (prog) {
          const pct = Math.round(i / total * 100);
          const icon = status === 'sent' ? '✅' : status === 'skip' ? '⏭️' : '❌';
          prog.innerHTML = `
            <div style="background:var(--bg2);border-radius:8px;padding:10px">
              <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:6px">
                <span>${icon} ${nama}</span>
                <span style="font-weight:600">${i}/${total} (${pct}%)</span>
              </div>
              <div style="background:var(--border);border-radius:4px;height:6px">
                <div style="background:var(--primary);height:100%;border-radius:4px;width:${pct}%;transition:width .3s"></div>
              </div>
            </div>`;
        }
      }
    }
  );

  const sent = results.filter(r => r.status === 'sent').length;
  const failed = results.filter(r => r.status === 'failed').length;
  const skipped = results.filter(r => r.status === 'skip').length;

  MPWA.saveLog({
    template: tpl?.nama || '(manual)',
    penerima: target === 'all' ? 'Semua Warga' : target,
    jumlah: withHP.length,
    pesan: msg.substring(0, 200),
    status: `✅${sent} ❌${failed} ⏭️${skipped}`,
    detail: results
  });

  if (prog) {
    prog.innerHTML = `<div style="background:var(--bg2);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:1.1rem;font-weight:700;margin-bottom:4px">Broadcast Selesai!</div>
      <div style="font-size:.85rem">✅ Terkirim: ${sent} &nbsp; ❌ Gagal: ${failed} &nbsp; ⏭️ Skip: ${skipped}</div>
    </div>`;
  }
  App.toast(`📡 Broadcast selesai: ${sent} terkirim, ${failed} gagal`, sent > 0 ? 'success' : 'error');
};

// === Fallback: send via wa.me ===
Pages._sendViaWAMe = function () {
  const msg = document.getElementById('mpwaMsg')?.value?.trim();
  if (!msg) { App.toast('Tulis pesan terlebih dahulu!', 'error'); return; }
  window.open(`https://wa.me/?text=${encodeURIComponent(msg)}`, '_blank');
  const tplId = document.getElementById('mpwaTplSelect')?.value || '';
  const tpl = MPWA.getTemplates().find(t => t.id === tplId);
  MPWA.saveLog({ template: tpl?.nama || '(manual)', penerima: 'wa.me (manual)', jumlah: 1, pesan: msg, status: 'manual' });
  App.toast('📱 Dibuka di wa.me', 'success');
};

Pages._logReminder = function (kkId) {
  const kk = DB.getById('keluarga', kkId);
  MPWA.saveLog({ template: 'Reminder Tunggakan', penerima: kk?.nama || kkId, jumlah: 1, status: 'dikirim' });
};

// === Send single reminder via API ===
Pages._sendSingleReminder = async function (kkId, btn) {
  if (!MPWA.isConfigured()) { App.toast('⚠️ MPWA belum dikonfigurasi', 'error'); Pages._showWASetup(); return; }
  const kk = DB.getById('keluarga', kkId);
  if (!kk?.noHP) { App.toast('No HP kosong', 'error'); return; }
  const tpl = MPWA.getTemplates().find(t => t.id === 'tpl_reminder') || MPWA.DEFAULT_TEMPLATES[0];
  const pesan = MPWA.resolveParams(tpl.teks, kk);
  if (btn) { btn.disabled = true; btn.textContent = '⏳...'; }
  try {
    await MPWA.send(kk.noHP, pesan, 'SukaWarga10 · RW 10');
    if (btn) { btn.textContent = '✅'; btn.classList.remove('btn-success'); btn.classList.add('btn-outline'); }
    MPWA.saveLog({ template: 'Reminder', penerima: kk.nama, jumlah: 1, pesan: pesan.substring(0, 100), status: 'dikirim' });
    App.toast(`✅ Reminder terkirim ke ${kk.nama}`, 'success');
  } catch (e) {
    if (btn) { btn.textContent = '❌ Retry'; btn.disabled = false; }
    App.toast(`❌ Gagal: ${e.message}`, 'error');
  }
};

Pages._sendAllReminder = async function () {
  if (!MPWA.isConfigured()) { App.toast('⚠️ MPWA belum dikonfigurasi', 'error'); Pages._showWASetup(); return; }
  const tahun = DB.getSetting('tahun', 2026);
  const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
  const currentMonth = monthKeys[new Date().getMonth()];
  const keluarga = DB.getAll('keluarga').filter(k => k.status === 'aktif' || !k.status);
  const tunggakan = keluarga.filter(kk => {
    const pad = DB.getIuranPadaringan(kk.id, tahun);
    return !(pad?.months?.[currentMonth] > 0);
  });
  const withHP = tunggakan.filter(kk => MPWA.formatWANum(kk.noHP));
  if (withHP.length === 0) { App.toast('Tidak ada penerima dengan No. HP', 'error'); return; }
  if (!confirm(`Kirim reminder ke ${withHP.length} warga tunggakan via MPWA API?`)) return;

  const tpl = MPWA.getTemplates().find(t => t.id === 'tpl_reminder') || MPWA.DEFAULT_TEMPLATES[0];
  App.toast(`⏳ Mengirim ke ${withHP.length} penerima...`, 'info');

  const results = await MPWA.broadcast(withHP, (r) => MPWA.resolveParams(tpl.teks, r), {
    delayMs: 2000,
    footerFn: () => 'SukaWarga10 · RW 10',
  });

  const sent = results.filter(r => r.status === 'sent').length;
  MPWA.saveLog({ template: 'Reminder Tunggakan (Batch)', penerima: 'Tunggakan', jumlah: withHP.length, status: `✅${sent}/${withHP.length}` });
  App.toast(`📡 Reminder selesai: ${sent}/${withHP.length} terkirim`, sent > 0 ? 'success' : 'error');
  App.render('mpwa');
};

// --- Template Manager ---
Pages._showTemplateManager = function () {
  const templates = MPWA.getTemplates();
  const KATEGORI = ['reminder', 'event', 'pengumuman', 'laporan', 'custom'];
  const KATEGORI_ICONS = { reminder: '🔔', event: '🎉', pengumuman: '📣', laporan: '📋', custom: '✏️' };

  App.showModal('📝 Manajemen Template', `
      <div style="margin-bottom:12px">
        <button class="btn btn-primary btn-sm" onclick="Pages._showTplForm()">➕ Template Baru</button>
      </div>
      <div class="data-table-wrapper">
        <table class="data-table">
          <thead><tr><th>Kategori</th><th>Nama Template</th><th>Aksi</th></tr></thead>
          <tbody>
          ${templates.map(t => `<tr>
            <td><span class="badge badge-info">${KATEGORI_ICONS[t.kategori] || '✏️'} ${t.kategori}</span></td>
            <td style="font-weight:600">${t.nama}</td>
            <td style="display:flex;gap:6px">
              <button class="btn btn-sm btn-primary" onclick="Pages._showTplForm('${t.id}')">✏️ Edit</button>
              <button class="btn btn-sm btn-danger" onclick="Pages._deleteTpl('${t.id}')">🗑️</button>
            </td>
          </tr>`).join('')}
          </tbody>
        </table>
      </div>
    `);
};

Pages._showTplForm = function (id) {
  const tpl = id ? MPWA.getTemplates().find(t => t.id === id) : null;
  const KATEGORI_ICONS = { reminder: '🔔', event: '🎉', pengumuman: '📣', laporan: '📋', custom: '✏️' };
  App.showModal(tpl ? '✏️ Edit Template' : '➕ Template Baru', `
      <div class="form-group"><label class="form-label">Nama Template</label>
        <input class="form-control" id="tplNama" value="${tpl?.nama || ''}" placeholder="Nama template">
      </div>
      <div class="form-group"><label class="form-label">Kategori</label>
        <select class="form-control" id="tplKategori">
          ${['reminder', 'event', 'pengumuman', 'laporan', 'custom'].map(k => `<option value="${k}" ${tpl?.kategori === k ? 'selected' : ''}>${KATEGORI_ICONS[k] || ''} ${k}</option>`).join('')}
        </select>
      </div>
      <div class="form-group"><label class="form-label">Teks Pesan</label>
        <textarea class="form-control" id="tplTeks" rows="10" placeholder="Tulis pesan... Parameter: {{nama_warga}} {{rt}} {{iuran_bulan}} {{tahun}} {{tunggakan}} {{tarif_sampah}} {{tarif_padaringan}}">${tpl?.teks || ''}</textarea>
      </div>
      <div style="font-size:.78rem;color:var(--text-tertiary);margin-bottom:12px">
        💡 Parameter: <code>{{nama_warga}}</code> <code>{{rt}}</code> <code>{{iuran_bulan}}</code> <code>{{tahun}}</code> <code>{{tunggakan}}</code> <code>{{tarif_sampah}}</code> <code>{{tarif_padaringan}}</code> <code>{{saldo_sampah}}</code>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary btn-block" onclick="Pages._saveTpl('${id || ''}')">💾 Simpan</button>
        <button class="btn" onclick="App.closeModal()">Batal</button>
      </div>
    `);
};

Pages._saveTpl = function (id) {
  const nama = document.getElementById('tplNama')?.value?.trim();
  const kategori = document.getElementById('tplKategori')?.value;
  const teks = document.getElementById('tplTeks')?.value?.trim();
  if (!nama || !teks) { App.toast('Nama dan teks harus diisi!', 'error'); return; }
  if (id) {
    DB.update('mpwaTemplates', id, { nama, kategori, teks });
    App.toast('✅ Template diperbarui', 'success');
  } else {
    DB.insert('mpwaTemplates', { nama, kategori, teks });
    App.toast('✅ Template baru disimpan', 'success');
  }
  App.closeModal();
  App.render('mpwa');
};

Pages._deleteTpl = function (id) {
  if (!confirm('Hapus template ini?')) return;
  DB.delete('mpwaTemplates', id, true);
  App.toast('🗑️ Template dihapus', 'warning');
  App.closeModal();
  setTimeout(() => App.render('mpwa'), 300);
};

// --- WA Setup with MPWA API Configuration ---
Pages._showWASetup = function () {
  const layanan = DB.getSetting('mpwaLayanan', 'mpwa');
  const namaDevice = DB.getSetting('mpwaDeviceName', '');
  const sender = DB.getSetting('mpwaSender', '');
  const delay = DB.getSetting('mpwaDelay', '8');
  const apiKey = DB.getSetting('mpwaApiKey', '');
  const urlMpwa = DB.getSetting('mpwaUrl', 'https://mpwa.jabnet.id');

  const sectionStyle = 'background:white;border:1px solid var(--border,#e5e7eb);border-radius:10px;padding:18px 20px;margin-bottom:14px';
  const sectionTitle = 'font-weight:700;font-size:.92rem;color:var(--text,#1e293b);margin-bottom:14px;padding-bottom:8px;border-bottom:2px solid var(--border,#e5e7eb)';
  const labelStyle = 'font-size:.82rem;font-weight:600;color:var(--text2,#475569);margin-bottom:4px;display:flex;align-items:center;gap:4px';
  const inputStyle = 'width:100%;padding:10px 12px;border:1px solid var(--border,#e5e7eb);border-radius:8px;font-size:.88rem;background:var(--bg,#f8fafc);color:var(--text,#1e293b)';

  App.showModal('⚙️ Pengaturan WhatsApp', `
    <!-- Pilih Layanan -->
    <div style="${sectionStyle}">
      <div style="${labelStyle}">Pilih Layanan Whatsapp</div>
      <select id="mpwaLayanan" style="${inputStyle}" onchange="Pages._onLayananChange()">
        <option value="mpwa" ${layanan === 'mpwa' ? 'selected' : ''}>MPWA</option>
        <option value="starsender" ${layanan === 'starsender' ? 'selected' : ''}>StarSender</option>
        <option value="fonnte" ${layanan === 'fonnte' ? 'selected' : ''}>Fonnte</option>
      </select>
    </div>

    <!-- Data Perangkat -->
    <div style="${sectionStyle}">
      <div style="${sectionTitle}">Data Perangkat</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div>
          <div style="${labelStyle}">Nama Perangkat / Device <span style="cursor:help;color:var(--primary)" title="Nama label perangkat untuk identifikasi">ℹ️</span></div>
          <input id="mpwaDeviceName" style="${inputStyle}" value="${namaDevice}" placeholder="Contoh: MPWA JABNET - 885">
        </div>
        <div>
          <div style="${labelStyle}">Nomor Telepon</div>
          <input id="mpwaSender" style="${inputStyle}" value="${sender}" placeholder="6289630599885" inputmode="numeric">
        </div>
      </div>
      <div style="margin-top:14px;max-width:50%">
        <div style="${labelStyle}">Delay <span style="cursor:help;color:var(--primary)" title="Jeda waktu antar pengiriman pesan untuk menghindari rate limit">ℹ️</span></div>
        <select id="mpwaDelay" style="${inputStyle}">
          ${[2, 4, 6, 8, 10, 15, 20, 30].map(d => `<option value="${d}" ${delay == d ? 'selected' : ''}>${d} detik</option>`).join('')}
        </select>
      </div>
    </div>

    <!-- Data Provider -->
    <div style="${sectionStyle}">
      <div style="${sectionTitle}">Data Provider</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div>
          <div style="${labelStyle}">API Key MPWA <span style="cursor:help;color:var(--primary)" title="API Key dari akun MPWA Anda">ℹ️</span></div>
          <input id="mpwaApiKey" style="${inputStyle};font-family:'Space Mono',monospace;font-size:.82rem" value="${apiKey}" placeholder="k5JcViSXt7a9dAtugADB3QTTVJuZKw">
        </div>
        <div>
          <div style="${labelStyle}">Url MPWA <span style="cursor:help;color:var(--primary)" title="URL endpoint API MPWA">ℹ️</span></div>
          <input id="mpwaUrl" style="${inputStyle}" value="${urlMpwa}" placeholder="https://mpwa.jabnet.id">
        </div>
      </div>
    </div>

    <!-- Auto-Broadcast Pembayaran -->
    <div style="${sectionStyle}">
      <div style="${sectionTitle}">Auto-Broadcast Pembayaran</div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" id="mpwaAutoBroadcast" ${DB.getSetting('mpwaAutoBroadcast', false) ? 'checked' : ''} style="width:18px;height:18px;accent-color:var(--primary)">
          <span style="font-size:.88rem;font-weight:600">Aktifkan auto-broadcast saat pembayaran masuk</span>
        </label>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div>
          <div style="${labelStyle}">Template Iuran Sampah</div>
          <select id="mpwaTplSampah" style="${inputStyle}">
            ${MPWA.getTemplates().map(t => `<option value="${t.id}" ${t.id === DB.getSetting('mpwaTplSampah', 'tpl_invoice_sampah') ? 'selected' : ''}>${t.nama}</option>`).join('')}
          </select>
        </div>
        <div>
          <div style="${labelStyle}">Template Iuran Padaringan</div>
          <select id="mpwaTplPadaringan" style="${inputStyle}">
            ${MPWA.getTemplates().map(t => `<option value="${t.id}" ${t.id === DB.getSetting('mpwaTplPadaringan', 'tpl_invoice_padaringan') ? 'selected' : ''}>${t.nama}</option>`).join('')}
          </select>
        </div>
      </div>
      <div style="margin-top:8px;font-size:.75rem;color:var(--text3)">
        💡 Parameter khusus invoice: <code>{{detail_periode}}</code> <code>{{detail_jumlah}}</code> <code>{{detail_tanggal}}</code>
      </div>
    </div>

    <!-- Status / Test Result -->
    <div id="mpwaTestResult" style="margin-bottom:12px"></div>

    <!-- Actions -->
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button class="btn btn-primary" onclick="Pages._saveWASetup()" style="padding:10px 28px;font-weight:600">💾 Simpan Nomor</button>
      <button class="btn btn-danger" onclick="App.closeModal()" style="padding:10px 28px;font-weight:600">Kembali</button>
    </div>
  `);
};

Pages._onLayananChange = function () {
  // Future: toggle fields based on selected provider
  const layanan = document.getElementById('mpwaLayanan')?.value;
  App.toast(`Layanan dipilih: ${layanan.toUpperCase()}`, 'info');
};

Pages._saveWASetup = function () {
  const layanan = document.getElementById('mpwaLayanan')?.value || 'mpwa';
  const namaDevice = document.getElementById('mpwaDeviceName')?.value?.trim();
  const sender = document.getElementById('mpwaSender')?.value?.trim();
  const delay = document.getElementById('mpwaDelay')?.value || '8';
  const apiKey = document.getElementById('mpwaApiKey')?.value?.trim();
  const urlMpwa = document.getElementById('mpwaUrl')?.value?.trim() || 'https://mpwa.jabnet.id';
  const autoBroadcast = document.getElementById('mpwaAutoBroadcast')?.checked || false;
  const tplSampah = document.getElementById('mpwaTplSampah')?.value || 'tpl_invoice_sampah';
  const tplPadaringan = document.getElementById('mpwaTplPadaringan')?.value || 'tpl_invoice_padaringan';

  if (!apiKey) { App.toast('API Key wajib diisi', 'error'); return; }
  if (!sender) { App.toast('Nomor Telepon wajib diisi', 'error'); return; }

  DB.setSetting('mpwaLayanan', layanan);
  DB.setSetting('mpwaDeviceName', namaDevice);
  DB.setSetting('mpwaSender', sender);
  DB.setSetting('mpwaDelay', delay);
  DB.setSetting('mpwaApiKey', apiKey);
  DB.setSetting('mpwaUrl', urlMpwa);
  DB.setSetting('mpwaAutoBroadcast', autoBroadcast);
  DB.setSetting('mpwaTplSampah', tplSampah);
  DB.setSetting('mpwaTplPadaringan', tplPadaringan);

  App.closeModal();
  App.render('mpwa');
  App.toast('✅ Pengaturan WhatsApp disimpan!', 'success');
};

Pages._testMPWA = async function () {
  const apiKey = document.getElementById('mpwaApiKey')?.value?.trim();
  const result = document.getElementById('mpwaTestResult');
  if (!apiKey) { App.toast('Masukkan API Key dulu', 'error'); return; }

  DB.setSetting('mpwaApiKey', apiKey);
  const urlMpwa = document.getElementById('mpwaUrl')?.value?.trim() || 'https://mpwa.jabnet.id';
  DB.setSetting('mpwaUrl', urlMpwa);

  if (result) result.innerHTML = '<div style="text-align:center;padding:8px;font-size:.85rem">⏳ Menguji koneksi...</div>';

  try {
    const info = await MPWA.testConnection();
    const devices = await MPWA.checkDevice();
    let deviceList = '';
    if (devices.data && Array.isArray(devices.data)) {
      deviceList = devices.data.map(d => {
        const online = d.status === 'Connected';
        return `<div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:.82rem">
          <span style="font-family:'Space Mono',monospace">${d.device || d.number || '—'}</span>
          <span class="badge ${online ? 'badge-success' : 'badge-danger'}">${online ? '🟢 Online' : '🔴 Offline'}</span>
        </div>`;
      }).join('');
    }
    if (result) result.innerHTML = `
      <div style="background:#d1fae5;border:1px solid #34d399;border-radius:8px;padding:12px;font-size:.82rem">
        <div style="font-weight:700;margin-bottom:6px">✅ Koneksi Berhasil!</div>
        <div>User: <strong>${info.data?.username || '—'}</strong></div>
        <div>Status: <strong>${info.data?.status || '—'}</strong></div>
        ${deviceList ? `<div style="margin-top:8px;border-top:1px solid #a7f3d0;padding-top:8px"><strong>Devices:</strong>${deviceList}</div>` : ''}
      </div>`;
    App.toast('✅ Koneksi MPWA berhasil!', 'success');
  } catch (e) {
    if (result) result.innerHTML = `<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:12px;font-size:.82rem">
      <div style="font-weight:700">❌ Gagal Koneksi</div>
      <div>${e.message}</div>
    </div>`;
    App.toast('❌ ' + e.message, 'error');
  }
};

// ===================================================================
// LAPORAN & ANALISIS
// ===================================================================
Pages.laporan = function () {
  return `
  <div class="tab-bar">
    <button class="tab-btn active" onclick="Pages._switchTab(this,'tabRanking')">🏆 Ranking RT</button>
    <button class="tab-btn" onclick="Pages._switchTab(this,'tabKepatuhan')">📊 Kepatuhan</button>
    <button class="tab-btn" onclick="Pages._switchTab(this,'tabBulanan')">📋 Laporan Bulanan</button>
    <button class="tab-btn" onclick="Pages._switchTab(this,'tabTahunan')">📈 Laporan Tahunan</button>
    <button class="tab-btn" onclick="Pages._switchTab(this,'tabSosial')">👥 Laporan Sosial</button>
  </div>
  <div id="tabRanking" class="tab-content active">${Pages._renderRanking()}</div>
  <div id="tabKepatuhan" class="tab-content">${Pages._renderKepatuhan()}</div>
  <div id="tabBulanan" class="tab-content">${Pages._renderBulanan()}</div>
  <div id="tabTahunan" class="tab-content">${Pages._renderTahunan()}</div>
  <div id="tabSosial" class="tab-content">${Pages._renderSosial()}</div>`;
};

Pages._switchTab = function (btn, tabId) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(tabId)?.classList.add('active');
};

Pages._renderRanking = function () {
  const s = DB.getStats();
  const sorted = [...s.perRT].sort((a, b) => (b.sampah + b.padaringan) - (a.sampah + a.padaringan));
  return `<div class="glass-card"><div class="card-header"><span class="card-title">🏆 Ranking RT — Pemasukan Tertinggi</span></div><div class="card-body"><div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Ranking</th><th>RT</th><th>Total KK</th><th>Sampah</th><th>Padaringan</th><th>Total</th></tr></thead><tbody>
    ${sorted.map((r, i) => `<tr><td><span style="font-size:1.2rem">${['🥇', '🥈', '🥉'][i] || i + 1}</span></td><td style="font-weight:700">${r.rt}</td><td>${r.totalKK}</td><td>${App.formatRp(r.sampah)}</td><td>${App.formatRp(r.padaringan)}</td><td style="font-weight:700;color:var(--accent-emerald)">${App.formatRp(r.sampah + r.padaringan)}</td></tr>`).join('')}
  </tbody></table></div></div></div>`;
};

Pages._renderKepatuhan = function () {
  const s = DB.getStats();
  return `<div class="glass-card"><div class="card-header"><span class="card-title">📊 Tingkat Kepatuhan per RT</span></div><div class="card-body"><div class="data-table-wrapper"><table class="data-table"><thead><tr><th>RT</th><th>Total KK</th><th>Lunas Sampah</th><th>% Sampah</th><th>Lunas Padaringan</th><th>% Padaringan</th></tr></thead><tbody>
    ${s.perRT.map(r => {
    const pS = r.totalKK ? Math.round(r.kkLunasSampah / r.totalKK * 100) : 0;
    const pP = r.totalKK ? Math.round(r.kkLunasPadaringan / r.totalKK * 100) : 0;
    return `<tr><td style="font-weight:700">${r.rt}</td><td>${r.totalKK}</td><td>${r.kkLunasSampah}</td><td><div class="mini-progress"><div class="mini-progress-fill" style="width:${pS}%"></div></div>${pS}%</td><td>${r.kkLunasPadaringan}</td><td><div class="mini-progress"><div class="mini-progress-fill" style="width:${pP}%"></div></div>${pP}%</td></tr>`;
  }).join('')}
  </tbody></table></div></div></div>`;
};

Pages._renderBulanan = function () {
  const tahun = DB.getSetting('tahun', 2026);
  const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
  const currentMonthIdx = new Date().getMonth();
  const bulan = monthKeys[currentMonthIdx];
  const rek = DB.getRekonsiliasi(bulan, tahun);
  const keluarga = DB.getAll('keluarga').filter(k => k.status !== 'pindah' && k.status !== 'meninggal');
  const bulanPad = String(currentMonthIdx + 1).padStart(2, '0');
  const pengeluaran = DB.getAll('pengeluaran').filter(p => p.tanggal?.startsWith(`${tahun}-${bulanPad}`));
  const lunas = [], belum = [];
  keluarga.forEach(kk => {
    const pad = DB.getIuranPadaringan(kk.id, tahun);
    if (pad?.months?.[bulan] > 0) lunas.push(kk);
    else belum.push(kk);
  });
  return `
  <div class="glass-card"><div class="card-header"><span class="card-title">📋 Laporan Bulanan — ${bulan} ${tahun}</span>
    <button class="btn btn-sm btn-primary" onclick="Pages._printLaporan()">🖨️ Cetak</button></div>
  <div class="card-body" id="laporanBulananPrint">
    <h3 style="text-align:center;margin-bottom:16px">LAPORAN KEUANGAN BULANAN<br><small>RW 10 Kel. Sukakarya — ${bulan} ${tahun}</small></h3>
    <h4 style="color:var(--accent-cyan);margin:16px 0 8px">📊 Rekonsiliasi</h4>
    <table class="data-table compact"><thead><tr><th>Kas</th><th>Tagihan</th><th>Terbayar</th><th>Outstanding</th><th>Kepatuhan</th><th>Pengeluaran</th><th>Saldo</th></tr></thead><tbody>
      <tr><td style="font-weight:600">🗑️ Sampah</td><td>${App.formatRp(rek.sampah.tagihan)}</td><td style="color:var(--accent-emerald)">${App.formatRp(rek.sampah.terbayar)}</td><td style="color:var(--accent-rose)">${App.formatRp(rek.sampah.outstanding)}</td><td>${rek.sampah.kepatuhan}%</td><td style="color:var(--accent-rose)">${App.formatRp(rek.sampah.pengeluaran)}</td><td style="font-weight:700">${App.formatRp(rek.sampah.saldo)}</td></tr>
      <tr><td style="font-weight:600">🤝 Padaringan</td><td>${App.formatRp(rek.padaringan.tagihan)}</td><td style="color:var(--accent-emerald)">${App.formatRp(rek.padaringan.terbayar)}</td><td style="color:var(--accent-rose)">${App.formatRp(rek.padaringan.outstanding)}</td><td>${rek.padaringan.kepatuhan}%</td><td style="color:var(--accent-rose)">${App.formatRp(rek.padaringan.pengeluaran)}</td><td style="font-weight:700">${App.formatRp(rek.padaringan.saldo)}</td></tr>
    </tbody></table>
    <h4 style="color:var(--accent-cyan);margin:16px 0 8px">✅ Sudah Bayar Padaringan (${lunas.length})</h4>
    <div class="tag-list">${lunas.map(k => `<span class="badge badge-lunas">${k.nama} (${k.rt})</span>`).join(' ')}</div>
    <h4 style="color:var(--accent-rose);margin:16px 0 8px">❌ Belum Bayar Padaringan (${belum.length})</h4>
    <div class="tag-list">${belum.map(k => `<span class="badge badge-belum">${k.nama} (${k.rt})</span>`).join(' ')}</div>
    ${pengeluaran.length ? `<h4 style="color:var(--accent-amber);margin:16px 0 8px">💸 Pengeluaran (${pengeluaran.length})</h4>
    <table class="data-table compact"><thead><tr><th>Tgl</th><th>Kas</th><th>Keterangan</th><th>Jumlah</th></tr></thead><tbody>
      ${pengeluaran.map(p => `<tr><td>${p.tanggal}</td><td>${p.kategori}</td><td>${p.keterangan}</td><td style="color:var(--accent-rose)">${App.formatRp(p.jumlah)}</td></tr>`).join('')}
    </tbody></table>` : ''}
  </div></div>`;
};

Pages._printLaporan = function () {
  const content = document.getElementById('laporanBulananPrint');
  if (!content) return;
  const w = window.open('', '_blank');
  w.document.write(`<html><head><title>Laporan Bulanan</title><style>body{font-family:Inter,sans-serif;padding:20px;color:#222}table{width:100%;border-collapse:collapse;margin:8px 0}th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;font-size:.85rem}th{background:#f0f0f0;font-weight:700}.badge-lunas{display:inline-block;background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:10px;font-size:.75rem;margin:2px}.badge-belum{display:inline-block;background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:10px;font-size:.75rem;margin:2px}.tag-list{display:flex;flex-wrap:wrap;gap:4px}</style></head><body>${content.innerHTML}</body></html>`);
  w.document.close(); w.print();
};

Pages._renderTahunan = function () {
  const tahun = DB.getSetting('tahun', 2026);
  const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
  let maxVal = 1;
  const monthData = monthKeys.map(bulan => {
    const rek = DB.getRekonsiliasi(bulan, tahun);
    const masuk = rek.sampah.terbayar + rek.padaringan.terbayar;
    const keluar = rek.sampah.pengeluaran + rek.padaringan.pengeluaran;
    if (masuk > maxVal) maxVal = masuk;
    if (keluar > maxVal) maxVal = keluar;
    return { bulan, masuk, keluar, kepatuhan: Math.round((rek.sampah.kepatuhan + rek.padaringan.kepatuhan) / 2) };
  });
  const pengeluaran = DB.getAll('pengeluaran');
  const catMap = {};
  pengeluaran.forEach(p => { const c = p.jenis || 'Lainnya'; catMap[c] = (catMap[c] || 0) + (p.jumlah || 0); });
  const totalExp = Object.values(catMap).reduce((s, v) => s + v, 0) || 1;
  const catColors = ['#3b82f6', '#10b981', '#f59e0b', '#f43f5e', '#8b5cf6', '#06b6d4'];
  return `
  <div class="glass-card"><div class="card-header"><span class="card-title">📈 Tren Bulanan — ${tahun}</span></div><div class="card-body">
    <div class="chart-legend"><span class="legend-item"><span class="legend-dot" style="background:var(--accent-emerald)"></span>Pemasukan</span><span class="legend-item"><span class="legend-dot" style="background:var(--accent-rose)"></span>Pengeluaran</span></div>
    <div class="bar-chart">${monthData.map(m => {
    const hIn = Math.round(m.masuk / maxVal * 120);
    const hOut = Math.round(m.keluar / maxVal * 120);
    return `<div class="bar-group"><div class="bar-pair"><div class="bar bar-in" style="height:${hIn}px" title="Masuk: ${App.formatRp(m.masuk)}"></div><div class="bar bar-out" style="height:${hOut}px" title="Keluar: ${App.formatRp(m.keluar)}"></div></div><div class="bar-label">${m.bulan}</div></div>`;
  }).join('')}</div>
  </div></div>
  <div class="dashboard-grid" style="margin-top:16px">
    <div class="glass-card"><div class="card-header"><span class="card-title">📊 Kepatuhan Bulanan</span></div><div class="card-body">
      ${monthData.map(m => `<div class="chart-bar-row"><span class="chart-label">${m.bulan}</span><div class="chart-bar-track"><div class="mini-progress-fill" style="width:${m.kepatuhan}%;background:${m.kepatuhan >= 50 ? 'var(--accent-emerald)' : 'var(--accent-rose)'}"></div></div><span class="chart-value">${m.kepatuhan}%</span></div>`).join('')}
    </div></div>
    <div class="glass-card"><div class="card-header"><span class="card-title">🍩 Komposisi Pengeluaran</span></div><div class="card-body">
      ${Object.entries(catMap).map(([cat, val], i) => {
    const pct = Math.round(val / totalExp * 100);
    return `<div class="chart-bar-row"><span class="chart-label">${cat}</span><div class="chart-bar-track"><div class="mini-progress-fill" style="width:${pct}%;background:${catColors[i % catColors.length]}"></div></div><span class="chart-value">${pct}% (${App.formatRp(val)})</span></div>`;
  }).join('')}
    </div></div>
  </div>`;
};

Pages._renderSosial = function () {
  const keluarga = DB.getAll('keluarga');
  const penghasilanMap = {};
  keluarga.forEach(k => { const p = k.penghasilan || 'Tidak diketahui'; penghasilanMap[p] = (penghasilanMap[p] || 0) + 1; });
  const totalKK = keluarga.length || 1;
  const tagCounts = { lansia: 0, difabel: 0, bansos: 0, janda: 0, prioritas: 0 };
  keluarga.forEach(k => (k.tags || []).forEach(t => { if (tagCounts[t] !== undefined) tagCounts[t]++; }));
  const s = DB.getStats();
  const pColors = ['#fee2e2', '#fef3c7', '#d1fae5', '#a7f3d0'];
  return `
  <div class="glass-card"><div class="card-header"><span class="card-title">💰 Distribusi Penghasilan</span></div><div class="card-body">
    ${Object.entries(penghasilanMap).map(([p, count]) => {
    const pct = Math.round(count / totalKK * 100);
    return `<div class="chart-bar-row"><span class="chart-label">${p}</span><div class="chart-bar-track"><div class="mini-progress-fill" style="width:${pct}%;background:var(--accent-blue)"></div></div><span class="chart-value">${count} KK (${pct}%)</span></div>`;
  }).join('')}
  </div></div>
  <div class="dashboard-grid" style="margin-top:16px">
    <div class="glass-card"><div class="card-header"><span class="card-title">🩺 Warga Rentan</span></div><div class="card-body">
      <div class="stat-items">
        <div class="stat-item"><span class="stat-icon">👴</span><span class="stat-val">${tagCounts.lansia}</span><span class="stat-lbl">Lansia</span></div>
        <div class="stat-item"><span class="stat-icon">♿</span><span class="stat-val">${tagCounts.difabel}</span><span class="stat-lbl">Difabel</span></div>
        <div class="stat-item"><span class="stat-icon">📦</span><span class="stat-val">${tagCounts.bansos}</span><span class="stat-lbl">Bansos</span></div>
        <div class="stat-item"><span class="stat-icon">👤</span><span class="stat-val">${tagCounts.janda}</span><span class="stat-lbl">Janda/Duda</span></div>
        <div class="stat-item"><span class="stat-icon">⭐</span><span class="stat-val">${tagCounts.prioritas}</span><span class="stat-lbl">Prioritas</span></div>
      </div>
    </div></div>
    <div class="glass-card"><div class="card-header"><span class="card-title">🗺️ Kepatuhan per RT</span></div><div class="card-body">
      <div class="rt-heatmap">
        ${s.perRT.map(r => {
    const pct = r.totalKK ? Math.round(r.kkLunasPadaringan / r.totalKK * 100) : 0;
    const bg = pct >= 75 ? pColors[3] : pct >= 50 ? pColors[2] : pct >= 25 ? pColors[1] : pColors[0];
    return `<div class="heatmap-cell" style="background:${bg}"><div class="heatmap-rt">${r.rt}</div><div class="heatmap-pct">${pct}%</div><div class="heatmap-sub">${r.kkLunasPadaringan}/${r.totalKK} KK</div></div>`;
  }).join('')}
      </div>
    </div></div>
  </div>
  <div class="glass-card" style="margin-top:16px"><div class="card-header"><span class="card-title">📋 Export Laporan untuk Kelurahan</span></div><div class="card-body">
    <button class="btn btn-primary" onclick="Pages._exportLaporanSosial()">📥 Export Data Sosial (CSV)</button>
    <button class="btn btn-success" style="margin-left:8px" onclick="Pages._exportWargaCSV()">📥 Export Data Warga (CSV)</button>
  </div></div>`;
};

Pages._exportLaporanSosial = function () {
  const keluarga = DB.getAll('keluarga');
  const rows = keluarga.map(k => ({ Nama: k.nama, RT: k.rt, Pekerjaan: k.pekerjaan || '', Penghasilan: k.penghasilan || '', Status: k.status || 'aktif', Tags: (k.tags || []).join('; '), NoHP: k.noHP || '', Alamat: k.alamat || '' }));
  DB.exportCSV(rows, `laporan_sosial_${new Date().toISOString().slice(0, 10)}.csv`);
  App.toast('Laporan sosial di-export!', 'success');
};

Pages._exportWargaCSV = function () {
  const keluarga = DB.getAll('keluarga');
  const rows = keluarga.map(k => ({ NoKK: k.noKK || '', NIK: k.nik || '', Nama: k.nama, RT: k.rt, Alamat: k.alamat || '', NoHP: k.noHP || '', Pekerjaan: k.pekerjaan || '', Penghasilan: k.penghasilan || '', StatusRumah: k.statusRumah || '', JumlahAnggota: k.jumlahAnggota || '', Status: k.status || '', Tags: (k.tags || []).join('; ') }));
  DB.exportCSV(rows, `data_warga_${new Date().toISOString().slice(0, 10)}.csv`);
  App.toast('Data warga di-export!', 'success');
};
