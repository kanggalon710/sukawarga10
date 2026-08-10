/* App — Router, Modal, Toast, Settings, Detail KK, Backup */
const App = {
  currentPage: 'dashboard',

  init() {
    // Guard: harus login
    if (!Auth.requireLogin()) return;
    DB.migrateData();
    Auth.initRoles();
    Auth.seedDefaultUsers();
    Auth.guardMenus();
    const hash = location.hash.replace('#', '') || 'dashboard';
    this.navigate(hash);
    this.checkBackup();
  },

  navigate(page) {
    this.currentPage = page;
    location.hash = page;
    this.render(page);
    // Update sidebar active
    document.querySelectorAll('.nav-item').forEach(el => el.classList.toggle('active', el.dataset.page === page));
    // Update bottom nav active
    document.querySelectorAll('.bnav-item').forEach(el => {
      const dp = el.dataset.page;
      const isActive = dp === page || (dp === 'billing' && (page === 'sampah' || page === 'padaringan' || page === 'setor'));
      el.classList.toggle('active', isActive);
    });
    // Close sidebar on mobile
    document.getElementById('sidebar')?.classList.remove('open');
    document.getElementById('sidebarOverlay')?.classList.remove('active');
  },

  render(page) {
    const main = document.getElementById('pageContent');
    if (!main) return;
    // === Route Guard: cek akses menu ===
    if (page !== 'dashboard' && typeof Auth !== 'undefined' && !Auth.canAccessMenu(page)) {
      page = 'dashboard';
      location.hash = 'dashboard';
    }
    const titles = {
      dashboard: 'Dashboard', pendataan: 'Data Warga', sampah: 'Iuran Sampah',
      padaringan: 'Iuran Padaringan', setor: 'Setor Sampah RT', pengeluaran: 'Pengeluaran',
      sumbangan: 'Sumbangan & Donasi', bukukas: 'Buku Kas', aduan: 'Aduan Warga',
      mpwa: 'MPWA Broadcast', laporan: 'Laporan & Analisis', surat: 'Surat Menyurat',
      umkm: 'UMKM Warga', users: 'Manajemen Akun', kegiatan: 'Kegiatan RW/RT'
    };
    document.getElementById('pageTitle').textContent = titles[page] || 'Dashboard';
    try {
      const routes = {
        dashboard: () => Pages.dashboard(),
        pendataan: () => Pages.pendataan(),
        sampah: () => Pages.iuran('sampah'),
        padaringan: () => Pages.iuran('padaringan'),
        setor: () => Pages.setor(),
        pengeluaran: () => Pages.pengeluaran(),
        sumbangan: () => Pages.sumbangan(),
        bukukas: () => Pages.bukukas(),
        aduan: () => Pages.aduan(),
        mpwa: () => Pages.mpwa(),
        laporan: () => Pages.laporan(),
        surat: () => Pages.surat(),
        umkm: () => Pages.umkm(),
        users: () => Pages.users(),
        auditlog: () => Pages.auditlog(),
        kegiatan: () => Pages.kegiatan()
      };
      main.innerHTML = (routes[page] || routes.dashboard)();
      main.scrollTop = 0;
    } catch (err) {
      main.innerHTML = `<div class="empty-state"><div class="empty-state-icon">⚠️</div><div class="empty-state-title">Error loading page</div><div class="empty-state-subtitle">${err.message}</div></div>`;
      console.error(err);
    }
  },

  // === Sidebar Toggle (Mobile) ===
  toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sidebarOverlay');
    sb.classList.toggle('open');
    ov.classList.toggle('active');
  },

  // === Modal ===
  showModal(title, body) {
    const overlay = document.getElementById('modalOverlay');
    const content = document.getElementById('modalContent');
    const safeTitle = this.escapeHtml(title);
    content.innerHTML = `<div class="modal-header"><h2 class="modal-title">${safeTitle}</h2><button class="modal-close" onclick="App.closeModal()">✕</button></div>${body}`;
    overlay.classList.add('show');
  },
  closeModal(e) {
    if (e && e.target !== e.currentTarget) return;
    document.getElementById('modalOverlay').classList.remove('show');
  },

  // === Toast ===
  toast(msg, type = 'info') {
    const container = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.textContent = msg;
    container.appendChild(t);
    setTimeout(() => t.remove(), 3000);
  },

  // === Format Rupiah ===
  formatRp(n) {
    return 'Rp ' + (n || 0).toLocaleString('id-ID');
  },

  // === XSS Sanitization ===
  escapeHtml(str) {
    if (str == null) return '';
    const s = String(str);
    return s
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  },

  // === Global Search ===
  globalSearch(q) {
    const dd = document.getElementById('searchDropdown');
    if (!q || q.length < 2) { dd.classList.remove('show'); dd.innerHTML = ''; return; }
    const results = DB.getAll('keluarga').filter(k => k.nama?.toLowerCase().includes(q.toLowerCase()) || k.noHP?.includes(q));
    dd.innerHTML = results.slice(0, 8).map(kk => `<div class="search-dropdown-item" onclick="App.showDetailKK('${this.escapeHtml(kk.id)}')">${this.escapeHtml(kk.nama)} <span style="font-size:0.72rem;color:var(--text-tertiary)">${this.escapeHtml(kk.rt)}</span></div>`).join('');
    dd.classList.toggle('show', results.length > 0);
  },

  // === Detail KK — 3 Tab Modal ===
  showDetailKK(id) {
    const kk = DB.getById('keluarga', id);
    if (!kk) return this.toast('Data KK tidak ditemukan', 'error');
    document.getElementById('searchDropdown').classList.remove('show');
    document.getElementById('globalSearch').value = '';
    const tab = Pages._detailTab || 'profil';
    const tahun = DB.getSetting('tahun', 2026);

    let content = `
    <div class="modal-header"><h2 class="modal-title">${kk.nama}</h2><button class="modal-close" onclick="App.closeModal()">✕</button></div>
    <div class="tab-nav">
      <button class="tab-btn ${tab === 'profil' ? 'active' : ''}" onclick="Pages._detailTab='profil';App.showDetailKK('${id}')">📋 Profil</button>
      <button class="tab-btn ${tab === 'anggota' ? 'active' : ''}" onclick="Pages._detailTab='anggota';App.showDetailKK('${id}')">👥 Anggota</button>
      <button class="tab-btn ${tab === 'iuran' ? 'active' : ''}" onclick="Pages._detailTab='iuran';App.showDetailKK('${id}')">💰 Riwayat Iuran</button>
      <button class="tab-btn ${tab === 'transaksi' ? 'active' : ''}" onclick="Pages._detailTab='transaksi';App.showDetailKK('${id}')">📖 Transaksi</button>
    </div>`;

    if (tab === 'profil') {
      content += `
      <div class="data-table-wrapper"><table class="data-table">
        <tr><td style="width:35%">RT / RW</td><td>${kk.rt} / RW ${kk.rw || '10'}</td></tr>
        <tr><td>Alamat</td><td>${kk.alamat || '-'}</td></tr>
        <tr><td>Kelurahan</td><td>${kk.kelurahan || '-'}</td></tr>
        <tr><td>No. HP</td><td>${kk.noHP || '-'}</td></tr>
        <tr><td>NIK</td><td>${kk.nik || '-'}</td></tr>
        <tr><td>No. KK</td><td>${kk.noKK || '-'}</td></tr>
        <tr><td>Jumlah Jiwa</td><td>${kk.jumlahAnggota || '-'}</td></tr>
        <tr><td>Status</td><td><span class="badge ${kk.status === 'aktif' ? 'badge-success' : 'badge-warning'}">${kk.status || 'aktif'}</span></td></tr>
      </table></div>
      ${kk.fotoKK ? `<div style="margin:8px 0"><strong style="font-size:12px">📄 Foto KK:</strong><br><img src="${kk.fotoKK}" style="max-width:100%;max-height:200px;border-radius:8px;border:1px solid var(--border);margin-top:4px;cursor:pointer" onclick="window.open(this.src)"></div>` : ''}
      <details style="margin-top:8px"><summary style="font-size:12px;font-weight:600;cursor:pointer;color:var(--text2)">🏠 Rumah & Sanitasi</summary>
      <div class="data-table-wrapper" style="margin-top:6px"><table class="data-table">
        <tr><td style="width:40%">Status Rumah</td><td>${kk.statusRumah || '-'}</td></tr>
        <tr><td>Tipe Bangunan</td><td>${kk.kondisiBangunan || '-'}</td></tr>
        <tr><td>Luas Lantai</td><td>${kk.luasLantai ? kk.luasLantai + ' m²' : '-'}</td></tr>
        <tr><td>Bahan Lantai/Dinding/Atap</td><td>${[kk.bahanLantai, kk.bahanDinding, kk.bahanAtap].filter(Boolean).join(' · ') || '-'}</td></tr>
        <tr><td>Air Minum / Mandi</td><td>${[kk.sumberAir, kk.sumberAirMandi].filter(Boolean).join(' / ') || '-'}</td></tr>
        <tr><td>Jamban</td><td>${kk.kepemilikanJamban || '-'}</td></tr>
        <tr><td>Sampah</td><td>${kk.caraSampah || '-'}</td></tr>
      </table></div></details>
      <details style="margin-top:6px"><summary style="font-size:12px;font-weight:600;cursor:pointer;color:var(--text2)">💼 Ekonomi & Bansos</summary>
      <div class="data-table-wrapper" style="margin-top:6px"><table class="data-table">
        <tr><td style="width:40%">Pekerjaan</td><td>${kk.pekerjaan || '-'}</td></tr>
        <tr><td>Penghasilan</td><td>${kk.penghasilan || '-'}</td></tr>
        <tr><td>Sumber Pendapatan</td><td>${kk.sumberPendapatan || '-'}</td></tr>
        <tr><td>Tabungan / Hutang</td><td>${kk.tabungan || '-'} / ${kk.hutangUsaha || '-'}</td></tr>
        <tr><td>BPJS</td><td>${kk.bpjs || '-'}</td></tr>
        <tr><td>Bansos</td><td>${(kk.bansos || []).join(', ') || '-'}</td></tr>
        <tr><td>Tag</td><td>${(kk.tags || []).map(t => `<span class="tag-chip">${t}</span>`).join(' ') || '-'}</td></tr>
      </table></div></details>
      <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
        ${Auth.can('canEdit') ? `<button class="btn btn-primary" onclick="App.closeModal();Pages.showFormKK('${id}')">✏️ Edit</button>` : ''}
        ${Auth.can('canDelete') ? `<button class="btn btn-danger" onclick="App.confirmDelete('keluarga','${id}','data KK ${kk.nama.replace(/'/g, "\\'")}')">🗑️ Hapus</button>` : ''}
      </div>`;
    } else if (tab === 'anggota') {
      const anggotaList = DB.getAll('anggota').filter(a => a.keluargaId === id);
      const rows = anggotaList.length ? anggotaList.map((a, i) => {
        const age = a.tglLahir ? Math.floor((Date.now() - new Date(a.tglLahir).getTime()) / 31557600000) : '?';
        return `<tr>
          <td>${i + 1}</td>
          <td><strong>${a.nama || '-'}</strong></td>
          <td>${a.jenisKelamin || '-'}</td>
          <td>${age} th</td>
          <td>${a.statusKeluarga || '-'}</td>
          <td>${a.pekerjaan || '-'}</td>
          <td>${a.bpjs || '-'}</td>
          <td style="white-space:nowrap">
            ${a.fotoKTP ? `<img src="${a.fotoKTP}" style="width:24px;height:16px;border-radius:2px;object-fit:cover;cursor:pointer;vertical-align:middle" onclick="event.stopPropagation();window.open(this.src)" title="Lihat KTP">` : ''}
            ${Auth.can('canEdit') ? `<button class="btn btn-sm btn-outline" style="padding:2px 6px;font-size:10px" onclick="App._showFormAnggota('${id}','${a.id}')" title="Edit">✏️</button>` : ''}
            ${Auth.can('canDelete') ? `<button class="btn btn-sm btn-danger" style="padding:2px 6px;font-size:10px" onclick="DB.delete('anggota','${a.id}');App.showDetailKK('${id}');App.toast('Anggota dihapus')" title="Hapus">🗑️</button>` : ''}
          </td>
        </tr> `;
      }).join('') : '<tr><td colspan="8" style="text-align:center;color:var(--text3);padding:16px">Belum ada data anggota keluarga</td></tr>';

      content += `
  <div class="data-table-wrapper"> <table class="data-table"><thead><tr><th>#</th><th>Nama</th><th>L/P</th><th>Usia</th><th>Status</th><th>Pekerjaan</th><th>BPJS</th><th>Aksi</th></tr></thead><tbody>
    ${rows}
  </tbody></table></div>
    <div style="font-size:11px;color:var(--text3);margin:6px 0">${anggotaList.length} anggota terdaftar</div>
      ${Auth.can('canEdit') ? `<button class="btn btn-primary btn-sm" onclick="App._showFormAnggota('${id}')">➕ Tambah Anggota</button>` : ''} `;
    } else if (tab === 'iuran') {
      const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
      const pad = DB.getIuranPadaringan(id, tahun);
      const samp = DB.getIuranSampah(id, tahun);

      // Mini grids
      content += `<h4 style = "margin:8px 0;font-size:0.85rem">🤝 Padaringan ${tahun}</h4>
      <div class="mini-grid">${monthKeys.map(m => `<div class="mini-cell ${pad?.months?.[m] > 0 ? 'paid' : ''}" title="${m}">${m.slice(0, 1)}</div>`).join('')}</div>
      <p style="font-size:0.75rem;color:var(--text-tertiary);margin:4px 0 12px">${monthKeys.filter(m => pad?.months?.[m] > 0).length}/12 bulan lunas</p>

      <h4 style="margin:8px 0;font-size:0.85rem">🗑️ Sampah ${tahun}</h4>
      <div class="mini-grid">${Array.from({ length: 52 }, (_, i) => {
        const w = 'M' + (i + 1);
        return `<div class="mini-cell ${samp?.weeks?.[w] > 0 ? 'paid' : ''}" title="${w}">${i + 1}</div>`;
      }).join('')}</div>
      <p style="font-size:0.75rem;color:var(--text-tertiary);margin:4px 0 12px">${Object.values(samp?.weeks || {}).filter(v => v > 0).length}/52 minggu lunas</p>`;
    } else {
      const trx = DB.getTransaksi({ tahun }).filter(t => t.refId === id).reverse();
      content += `<div class="data-table-wrapper"> <table class="data-table"><thead><tr><th>Tanggal</th><th>Keterangan</th><th>Debit</th><th>Kredit</th></tr></thead><tbody>
  ${trx.length ? trx.map(t => `<tr><td style="white-space:nowrap">${t.tanggal || '-'}</td><td>${t.keterangan}</td><td class="text-success">${t.jenis === 'masuk' ? App.formatRp(t.jumlah) : ''}</td><td class="text-danger">${t.jenis === 'keluar' ? App.formatRp(t.jumlah) : ''}</td></tr>`).join('')
          : '<tr><td colspan="4" class="text-muted text-center">Belum ada transaksi</td></tr>'}
</tbody></table></div> `;
    }

    const overlay = document.getElementById('modalOverlay');
    const mc = document.getElementById('modalContent');
    mc.innerHTML = content;
    mc.classList.add('modal-xl');
    overlay.classList.add('show');
  },

  // === Anggota Keluarga Form (Add/Edit) ===
  _showFormAnggota(keluargaId, anggotaId) {
    const isEdit = !!anggotaId;
    const a = isEdit ? DB.getById('anggota', anggotaId) : {};
    const sel = (cur, val) => cur === val ? 'selected' : '';
    const fotoPreview = a.fotoKTP ? `<img src="${a.fotoKTP}" style="max-height:60px;border-radius:6px;border:1px solid var(--border);margin-bottom:4px" id="angKtpPreview">` : '<div id="angKtpPreview"></div>';

    this.showModal(isEdit ? '✏️ Edit Anggota Keluarga' : '➕ Tambah Anggota Keluarga', `
  <div class="form-row-2">
        <div class="form-group"><label class="form-label">Nama Lengkap *</label><input class="form-input" id="fAngNama" placeholder="Nama anggota" value="${a.nama || ''}"></div>
        <div class="form-group"><label class="form-label">Jenis Kelamin</label>
          <select class="form-select" id="fAngJK"><option value="L" ${sel(a.jenisKelamin, 'L')}>Laki-laki</option><option value="P" ${sel(a.jenisKelamin, 'P')}>Perempuan</option></select>
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Tanggal Lahir</label><input type="date" class="form-input" id="fAngTglLahir" value="${a.tglLahir || ''}"></div>
        <div class="form-group"><label class="form-label">Status dalam Keluarga</label>
          <select class="form-select" id="fAngStatus"><option ${sel(a.statusKeluarga, 'Kepala Keluarga')}>Kepala Keluarga</option><option ${sel(a.statusKeluarga, 'Istri')}>Istri</option><option ${sel(a.statusKeluarga, 'Anak')}>Anak</option><option ${sel(a.statusKeluarga, 'Menantu')}>Menantu</option><option ${sel(a.statusKeluarga, 'Cucu')}>Cucu</option><option ${sel(a.statusKeluarga, 'Orang Tua')}>Orang Tua</option><option ${sel(a.statusKeluarga, 'Lainnya')}>Lainnya</option></select>
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-group"><label class="form-label">Pekerjaan</label><input class="form-input" id="fAngPekerjaan" placeholder="Pelajar, Wiraswasta, dll" value="${a.pekerjaan || ''}"></div>
        <div class="form-group"><label class="form-label">BPJS</label>
          <select class="form-select" id="fAngBPJS"><option value="">—</option><option ${sel(a.bpjs, 'Ya')}>Ya</option><option ${sel(a.bpjs, 'Tidak')}>Tidak</option></select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">NIK</label><input class="form-input" id="fAngNIK" maxlength="16" inputmode="numeric" placeholder="16 digit (opsional)" value="${a.nik || ''}"></div>
      <div class="form-group">
        <label class="form-label">📷 Foto KTP (opsional)</label>
        ${fotoPreview}
        <input type="file" accept="image/*" class="form-input" id="fAngFotoKTP" onchange="Pages._previewUpload(this,'angKtpPreview')" style="margin-top:4px">
        <div style="font-size:10px;color:var(--text3);margin-top:2px">Format: JPG/PNG, maks 2MB</div>
      </div>
      <div class="form-group"><label class="form-label">Keterangan</label><input class="form-input" id="fAngKet" placeholder="Catatan tambahan" value="${a.keterangan || ''}"></div>
      <button class="btn btn-primary btn-block" onclick="App._saveAnggota('${keluargaId}','${anggotaId || ''}')">${isEdit ? '💾 Simpan Perubahan' : '💾 Simpan Anggota'}</button>
`);
  },

  _saveAnggota(keluargaId, anggotaId) {
    const nama = document.getElementById('fAngNama')?.value?.trim();
    if (!nama) return this.toast('Nama anggota wajib diisi', 'warning');

    let fotoKTP = '';
    const preview = document.getElementById('angKtpPreview');
    if (preview?.tagName === 'IMG' && preview.src?.startsWith('data:')) fotoKTP = preview.src;
    else if (preview?.querySelector('img')?.src?.startsWith('data:')) fotoKTP = preview.querySelector('img').src;

    const data = {
      keluargaId,
      nama,
      jenisKelamin: document.getElementById('fAngJK')?.value || 'L',
      tglLahir: document.getElementById('fAngTglLahir')?.value || '',
      statusKeluarga: document.getElementById('fAngStatus')?.value || '',
      pekerjaan: document.getElementById('fAngPekerjaan')?.value?.trim() || '',
      bpjs: document.getElementById('fAngBPJS')?.value || '',
      nik: document.getElementById('fAngNIK')?.value?.trim() || '',
      keterangan: document.getElementById('fAngKet')?.value?.trim() || '',
    };

    // Keep existing foto if no new one uploaded
    if (fotoKTP) data.fotoKTP = fotoKTP;
    else if (anggotaId) {
      const existing = DB.getById('anggota', anggotaId);
      if (existing?.fotoKTP) data.fotoKTP = existing.fotoKTP;
    }

    if (anggotaId) {
      // Edit existing
      DB.update('anggota', anggotaId, data);
      this.toast('✅ Data anggota berhasil diperbarui', 'success');
    } else {
      // Add new
      data.fotoKTP = fotoKTP;
      DB.insert('anggota', data);
      this.toast('✅ Anggota berhasil ditambahkan', 'success');
    }

    // Update jumlahAnggota on the parent KK
    const count = DB.getAll('anggota').filter(a => a.keluargaId === keluargaId).length;
    DB.update('keluarga', keluargaId, { jumlahAnggota: count });

    this.closeModal();
    Pages._detailTab = 'anggota';
    this.showDetailKK(keluargaId);
  },

  // === Simple Delete Confirmation ===
  confirmDelete(collection, id, label) {
    // === Permission Guard ===
    if (typeof Auth !== 'undefined' && !Auth.can('canDelete')) {
      return this.toast('⛔ Anda tidak memiliki izin untuk menghapus data', 'error');
    }
    const safeLabel = this.escapeHtml(label);
    const safeId = this.escapeHtml(id);
    const safeColl = this.escapeHtml(collection);
    this.showModal('Konfirmasi Hapus', `
  <div style = "text-align:center;padding:12px 0">
        <div style="font-size:2.5rem;margin-bottom:8px">⚠️</div>
        <p style="font-size:0.92rem;margin-bottom:4px">Anda yakin ingin menghapus:</p>
        <p style="font-size:1rem;font-weight:700;color:var(--accent-rose);margin-bottom:16px">${safeLabel}</p>
        ${collection === 'keluarga' ? '<p style="font-size:0.78rem;color:var(--text-tertiary);margin-bottom:16px">Riwayat iuran terkait juga akan dihapus</p>' : ''}
<div style="display:flex;gap:8px;justify-content:center">
  <button class="btn" onclick="App.closeModal()">Batal</button>
  <button class="btn btn-danger btn-lg" onclick="App._doDelete('${safeColl}','${safeId}')">🗑️ Ya, Hapus</button>
</div>
      </div>
  `);
  },

  _doDelete(collection, id) {
    if (collection === 'keluarga') {
      // Also remove iuran records
      const tahun = DB.getSetting('tahun', 2026);
      const sampah = DB.query('iuranSampah', r => r.keluargaId === id);
      sampah.forEach(s => DB.delete('iuranSampah', s.id));
      const padaringan = DB.query('iuranPadaringan', r => r.keluargaId === id);
      padaringan.forEach(p => DB.delete('iuranPadaringan', p.id));
    }
    DB.delete(collection, id);
    this.closeModal();
    this.render(this.currentPage);
    this.toast('🗑️ Data berhasil dihapus', 'warning');
  },

  // === Settings & Profile ===
  showProfileOrSettings() {
    if (typeof Auth !== 'undefined' && Auth.can('canSettings')) {
      this.showSettings();
    } else {
      this.showProfile();
    }
  },

  showProfile() {
    let namaUser = 'User';
    let roleUser = 'Warga';
    let iconUser = '👤';
    let colorUser = 'var(--hijau)';

    if (typeof Auth !== 'undefined') {
      const session = Auth.getSession();
      if (session) {
        namaUser = session.namaLengkap || session.username;
        const info = Auth.getLevelInfo(session.level);
        roleUser = `${info.label}${session.rt ? ' — ' + session.rt : ''} `;
        iconUser = info.icon || iconUser;
        colorUser = info.color || colorUser;
      }
    }

    const initials = (namaUser || 'U').slice(0, 2).toUpperCase();

    this.showModal('🧑‍💼 Profil Saya', `
  <div style = "text-align:center;padding:20px 0">
        <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg, ${colorUser}, ${colorUser}aa);color:white;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;margin:0 auto 16px;box-shadow:var(--shadow-lg)">
          ${initials}
        </div>
        <h3 style="font-size:1.2rem;margin-bottom:4px;color:var(--text)">${namaUser}</h3>
        <p style="font-size:0.95rem;color:var(--text2);margin-bottom:24px">${iconUser} ${roleUser}</p>
        
        <button class="btn btn-danger btn-block" style="padding:12px;font-size:14px" onclick="Auth.logout()">🚪 Keluar Sistem (Logout)</button>
      </div>
  `);
  },

  showSettings() {
    const tahun = DB.getSetting('tahun', 2026);
    const tarifSampah = DB.getSetting('tarifSampah', 5000);
    const tarifPadaringan = DB.getSetting('tarifPadaringan', 5000);
    const operator = DB.getSetting('operator', 'Admin');
    const bendahara = DB.getSetting('bendahara', 'Bendahara');

    this.showModal('⚙️ Pengaturan', `
  <div class="tab-nav" style = "margin-bottom:12px">
        <button class="tab-btn active" onclick="this.parentElement.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));this.classList.add('active');document.getElementById('setTab1').style.display='block';document.getElementById('setTab2').style.display='none'">💰 Tarif & Umum</button>
        <button class="tab-btn" onclick="this.parentElement.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));this.classList.add('active');document.getElementById('setTab1').style.display='none';document.getElementById('setTab2').style.display='block'">🏘️ Info RW</button>
      </div>
      <div id="setTab1">
        <div class="form-row form-row-2">
          <div class="form-group"><label class="form-label">Tahun Aktif</label><input type="number" class="form-input" id="fSetTahun" value="${tahun}"></div>
          <div class="form-group"><label class="form-label">Operator</label><input type="text" class="form-input" id="fSetOperator" value="${operator}"></div>
        </div>
        <div class="form-row form-row-2">
          <div class="form-group"><label class="form-label">Tarif Sampah / minggu</label><input type="number" class="form-input" id="fSetTarifS" value="${tarifSampah}"></div>
          <div class="form-group"><label class="form-label">Tarif Padaringan / bulan</label><input type="number" class="form-input" id="fSetTarifP" value="${tarifPadaringan}"></div>
        </div>
        <div class="form-group"><label class="form-label">Nama Bendahara (kwitansi)</label><input type="text" class="form-input" id="fSetBendahara" value="${bendahara}"></div>
      </div>
      <div id="setTab2" style="display:none">
        <div class="form-row form-row-2">
          <div class="form-group"><label class="form-label">Nama RW</label><input type="text" class="form-input" id="fSetNamaRW" value="${DB.getSetting('namaRW', 'RW 10')}"></div>
          <div class="form-group"><label class="form-label">Kelurahan</label><input type="text" class="form-input" id="fSetKelurahan" value="${DB.getSetting('kelurahan', 'Sukakarya')}"></div>
        </div>
        <div class="form-row form-row-2">
          <div class="form-group"><label class="form-label">Kecamatan</label><input type="text" class="form-input" id="fSetKecamatan" value="${DB.getSetting('kecamatan', 'Tarogong Kidul')}"></div>
          <div class="form-group"><label class="form-label">Kabupaten</label><input type="text" class="form-input" id="fSetKabupaten" value="${DB.getSetting('kabupaten', 'Garut')}"></div>
        </div>
        <div class="form-row form-row-2">
          <div class="form-group"><label class="form-label">Ketua RW</label><input type="text" class="form-input" id="fSetKetuaRW" value="${DB.getSetting('ketuaRW', '')}"></div>
          <div class="form-group"><label class="form-label">No. HP Ketua RW</label><input type="text" class="form-input" id="fSetHPKetuaRW" value="${DB.getSetting('hpKetuaRW', '')}"></div>
        </div>
        <div class="form-group"><label class="form-label">Periode Kepengurusan</label><input type="text" class="form-input" id="fSetPeriode" value="${DB.getSetting('periode', '2024-2029')}" placeholder="2024-2029"></div>
      </div>
      <button class="btn btn-primary btn-block mt-3" onclick="App.saveSettings()">💾 Simpan Setting</button>
      <hr style="border-color:var(--border-glass);margin:16px 0">
      <h3 style="font-size:0.92rem;margin-bottom:8px">Data & Backup</h3>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-success" onclick="App.doBackup()">💾 Backup Data</button>
        <button class="btn" onclick="App.doRestore()">📥 Restore Data</button>
        <button class="btn" onclick="DB.seedDemoData();App.closeModal();App.render(App.currentPage);App.toast('Demo data loaded','success')">🧪 Demo Data</button>
      </div>
      <hr style="border-color:var(--border-glass);margin:16px 0">
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-danger" onclick="if(confirm('Hapus SEMUA data? Tidak bisa dikembalikan!')){localStorage.clear();location.reload();}">🗑️ Reset Semua Data</button>
      </div>
    `);
  },

  saveSettings() {
    DB.setSetting('tahun', parseInt(document.getElementById('fSetTahun').value) || 2026);
    DB.setSetting('operator', document.getElementById('fSetOperator').value);
    DB.setSetting('tarifSampah', parseInt(document.getElementById('fSetTarifS').value) || 5000);
    DB.setSetting('tarifPadaringan', parseInt(document.getElementById('fSetTarifP').value) || 5000);
    DB.setSetting('bendahara', document.getElementById('fSetBendahara').value);
    // Info RW (may not be visible if tab 2 not opened)
    const rwFields = { namaRW: 'fSetNamaRW', kelurahan: 'fSetKelurahan', kecamatan: 'fSetKecamatan', kabupaten: 'fSetKabupaten', ketuaRW: 'fSetKetuaRW', hpKetuaRW: 'fSetHPKetuaRW', periode: 'fSetPeriode' };
    Object.entries(rwFields).forEach(([key, id]) => { const el = document.getElementById(id); if (el) DB.setSetting(key, el.value); });
    this.closeModal();
    this.render(this.currentPage);
    this.toast('✅ Pengaturan disimpan', 'success');
  },

  // === Backup / Restore ===
  doBackup() {
    const data = DB.exportAll(); // returns a JSON string
    const blob = new Blob([data], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `backup-rw10-${new Date().toISOString().slice(0, 10)}.json`;
    a.click();
    DB.markBackup();
    const bn = document.getElementById('backupNotif');
    if (bn) bn.style.display = 'none';
    this.toast('💾 Backup berhasil diunduh', 'success');
  },

  doRestore() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    input.onchange = (e) => {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = (ev) => {
        try {
          const parsed = JSON.parse(ev.target.result);
          if (!parsed._version) throw new Error('Format file tidak valid (bukan backup RW10)');
          const ok = DB.importAll(ev.target.result);
          if (!ok) throw new Error('Gagal memproses data');
          this.closeModal();
          this.render(this.currentPage);
          this.toast('✅ Data berhasil di-restore', 'success');
        } catch (err) {
          this.toast('❌ Gagal restore: ' + err.message, 'error');
        }
      };
      reader.readAsText(file);
    };
    input.click();
  },

  checkBackup() {
    // === Permission Guard: hanya user yang boleh backup ===
    if (typeof Auth !== 'undefined' && !Auth.can('canBackup')) return;
    const needsBackup = DB.checkBackup();
    if (needsBackup) {
      const bn = document.getElementById('backupNotif');
      const msg = document.getElementById('backupMsg');
      const last = DB.getSetting('lastBackupDate', null);
      if (bn && msg) {
        msg.textContent = last ? `⚠️ Backup terakhir: ${last}. Segera backup data!` : '⚠️ Belum pernah backup! Segera backup data Anda.';
        bn.style.display = 'flex';
      }
    }
  },

  // === Export Warga CSV ===
  // === Logout ===
  logout() {
    if (typeof Auth !== 'undefined') Auth.logout();
    else { localStorage.removeItem('sukawarga10_session'); window.location.href = 'login.html'; }
  },

  exportWargaCSV() {
    const keluarga = DB.getAll('keluarga');
    const rows = keluarga.map((k, i) => ({
      No: i + 1,
      'No.KK': k.noKK || '',
      'Nama KK': k.nama || '',
      RT: k.rt || '',
      Alamat: k.alamat || '',
      'Jumlah Jiwa': k.jumlahAnggota || '',
      Pekerjaan: k.pekerjaan || '',
      'Penghasilan': k.penghasilan || '',
      'Status Rumah': k.statusRumah || '',
      Status: k.status || 'aktif',
      'Ikut Sampah': k.ikutSampah !== false ? 'Ya' : 'Tidak',
      'Ikut Padaringan': k.ikutPadaringan !== false ? 'Ya' : 'Tidak',
      Tags: (k.tags || []).join('; ')
    }));
    DB.exportCSV(rows, `data-warga-rw10-${new Date().getFullYear()}.csv`);
    App.toast('📥 Data warga diunduh', 'success');
  }
};

// Startup
document.addEventListener('DOMContentLoaded', () => App.init());
window.addEventListener('hashchange', () => {
  const page = location.hash.replace('#', '') || 'dashboard';
  App.navigate(page);
});
