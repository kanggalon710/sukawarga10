/* Pages — Surat Menyurat (10 jenis, nomor baku pemerintah) */
(function () {
  window.Pages = window.Pages || {};
  const Pages = window.Pages;

  // === Katalog Jenis Surat ===
  Pages._suratJenis = {
    skd: { kode: 'SKD', nama: 'Surat Keterangan Domisili', icon: '🏠' },
    sktm: { kode: 'SKTM', nama: 'Surat Keterangan Tidak Mampu', icon: '📋' },
    pengantar: { kode: 'SKP', nama: 'Surat Pengantar KTP/KK', icon: '🪪' },
    sku: { kode: 'SKU', nama: 'Surat Keterangan Usaha', icon: '🏪' },
    skck: { kode: 'SKCK', nama: 'Surat Pengantar SKCK', icon: '🚓' },
    skl: { kode: 'SKL', nama: 'Surat Keterangan Lahir', icon: '👶' },
    skm: { kode: 'SKM', nama: 'Surat Keterangan Kematian', icon: '🕊️' },
    skpindah: { kode: 'SKPINDAH', nama: 'Surat Keterangan Pindah Domisili', icon: '🚚' },
    skbm: { kode: 'SKBM', nama: 'Surat Keterangan Belum Menikah', icon: '💍' },
    skkb: { kode: 'SKKB', nama: 'Surat Keterangan Kelakuan Baik', icon: '✅' }
  };

  Pages.surat = function () {
    const tahun = DB.getSetting('tahun', 2026);
    const levelInfo = Auth.getLevelInfo(Auth.currentLevel());
    const isReadOnly = !!levelInfo.readOnly;
    const session = Auth.getSession();
    let suratList = DB.getAll('surat').filter(s => s.tahun == tahun).sort((a, b) => (b.createdAt || '').localeCompare(a.createdAt || ''));
    const keluarga = DB.getAll('keluarga');

    // === Data Filter: Warga hanya lihat surat milik sendiri ===
    if (isReadOnly && session) {
      suratList = suratList.filter(s =>
        s.requestedByUserId === session.id ||
        s.keluargaId && keluarga.find(k => k.id === s.keluargaId && k.nama?.toLowerCase() === session.namaLengkap?.toLowerCase())
      );
    }

    // === Data Filter: Petugas RT hanya lihat surat RT sendiri ===
    if (levelInfo.rtFilter && session?.rt) {
      const rtKKIds = keluarga.filter(k => k.rt === session.rt).map(k => k.id);
      suratList = suratList.filter(s => rtKKIds.includes(s.keluargaId));
    }

    const filterSurat = Pages._filterSurat || '';

    // Stats per jenis
    const jenisEntries = Object.entries(Pages._suratJenis);
    const displayed = filterSurat ? suratList.filter(s => s.jenis === filterSurat) : suratList;

    return `
  <div class="toolbar">
    <div class="toolbar-left">
      <span style="font-size:13px;color:var(--text2)">📜 ${suratList.length} surat tahun ${tahun}</span>
      ${(() => {
        const pending = suratList.filter(s => !s.status || s.status === 'diajukan' || s.status === 'disetujui_rt').length;
        return pending ? `<span class="badge badge-warning" style="margin-left:8px">⏳ ${pending} menunggu approval</span>` : '';
      })()}
    </div>
    <div class="toolbar-right">
      ${!isReadOnly ? '<button class="btn btn-primary" onclick="Pages._showFormSurat()">✏️ Buat Surat Baru</button>' : ''}
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:20px">
    ${jenisEntries.map(([k, j]) => {
        const cnt = suratList.filter(s => s.jenis === k).length;
        return `<div class="stat-card" style="cursor:pointer;${filterSurat === k ? 'border:2px solid var(--hijau)' : ''}" onclick="Pages._filterSurat='${k}';App.render('surat')">
        <div class="stat-icon-box">${j.icon}</div>
        <div class="stat-label">${j.kode}</div>
        <div class="stat-value">${cnt}</div>
      </div>`;
      }).join('')}
  </div>

  <div class="card">
    <div class="toolbar" style="margin-bottom:12px">
      <div class="toolbar-left"><h3 style="font-size:14px;font-weight:700">Riwayat Surat</h3></div>
      <div class="toolbar-right">
        <button class="btn btn-outline" style="font-size:12px" onclick="Pages._filterSurat='';App.render('surat')">Semua Jenis</button>
        <select class="filter-select" onchange="Pages._filterSurat=this.value;App.render('surat')">
          <option value="">Semua Jenis</option>
          ${jenisEntries.map(([k, j]) => `<option value="${k}" ${filterSurat === k ? 'selected' : ''}>${j.icon} ${j.nama}</option>`).join('')}
        </select>
      </div>
    </div>
    <div class="data-table-wrapper">
      <table class="data-table">
        <thead><tr><th>No. Surat</th><th>Tanggal</th><th>Nama</th><th>Jenis</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          ${displayed.length ? displayed.map(s => {
        const kk = keluarga.find(k => k.id === s.keluargaId);
        const j = Pages._suratJenis[s.jenis] || {};
        const st = s.status || 'diajukan';
        const stMap = {
          diajukan: { label: '📝 Diajukan', cls: 'badge-info' },
          disetujui_rt: { label: '✅ Disetujui RT', cls: 'badge-warning' },
          disetujui_rw: { label: '✅ Disetujui RW', cls: 'badge-success' },
          dicetak: { label: '🖨️ Dicetak', cls: 'badge-success' },
          ditolak_rt: { label: '❌ Ditolak RT', cls: 'badge-danger' },
          ditolak_rw: { label: '❌ Ditolak RW', cls: 'badge-danger' }
        };
        const stInfo = stMap[st] || stMap.diajukan;
        const level = Auth.currentLevel();
        const canApproveRT = (level === 'petugas_rt' || level === 'superadmin') && st === 'diajukan';
        const canApproveRW = (level === 'ketua_rw' || level === 'superadmin') && st === 'disetujui_rt';
        const canCetak = st === 'disetujui_rw' || st === 'dicetak';

        return `<tr>
              <td class="td-mono" style="font-size:11px">${s.nomorSurat}</td>
              <td style="white-space:nowrap">${s.tanggal || '-'}</td>
              <td><strong>${kk?.nama || s.namaPemohon || '-'}</strong> <span class="badge badge-gray" style="font-size:10px">${kk?.rt || ''}</span></td>
              <td><span class="badge badge-green">${j.icon || ''} ${j.kode || s.jenis}</span></td>
              <td><span class="badge ${stInfo.cls}" style="font-size:10px">${stInfo.label}</span>${s.alasanTolak ? `<div style="font-size:10px;color:var(--merah);margin-top:2px">💬 ${s.alasanTolak}</div>` : ''}</td>
              <td style="white-space:nowrap">
                ${canApproveRT ? `<button class="btn btn-success" style="padding:3px 8px;font-size:11px" onclick="Pages._approveSurat('${s.id}','disetujui_rt')">✅ Setujui RT</button> <button class="btn btn-danger" style="padding:3px 8px;font-size:11px" onclick="Pages._rejectSurat('${s.id}','ditolak_rt')">❌ Tolak</button>` : ''}
                ${canApproveRW ? `<button class="btn btn-success" style="padding:3px 8px;font-size:11px" onclick="Pages._approveSurat('${s.id}','disetujui_rw')">✅ Setujui RW</button> <button class="btn btn-danger" style="padding:3px 8px;font-size:11px" onclick="Pages._rejectSurat('${s.id}','ditolak_rw')">❌ Tolak</button>` : ''}
                ${canCetak ? `<button class="btn" style="padding:3px 8px;font-size:11px" onclick="Pages._cetakSurat('${s.id}')">🖨️ Cetak</button>` : ''}
                ${!canCetak && !canApproveRT && !canApproveRW && (st === 'diajukan' || st === 'disetujui_rt') ? `<span style="font-size:10px;color:var(--text3)">⏳ Menunggu...</span>` : ''}
                ${Auth.can('canDelete') ? `<button class="btn btn-danger" style="padding:3px 6px;font-size:11px;margin-left:4px" onclick="App.confirmDelete('surat','${s.id}','Surat ${s.nomorSurat}')">🗑️</button>` : ''}
              </td>
            </tr>`;
      }).join('')
        : '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">📜</div><div class="empty-state-title">Belum ada surat</div><div class="empty-state-subtitle">Buat surat baru dengan klik tombol di atas</div></div></td></tr>'}
        </tbody>
      </table>
    </div>
  </div>`;
  };

  Pages._showFormSurat = function (suratId) {
    const keluarga = DB.getAll('keluarga').filter(k => (k.status || 'aktif') === 'aktif');
    const tahun = DB.getSetting('tahun', 2026);
    const existing = suratId ? DB.getById('surat', suratId) : null;

    // Generate preview nomor surat
    const jenisEntries = Object.entries(Pages._suratJenis);

    App.showModal('✏️ Buat Surat Baru', `
    <div class="form-group">
      <label class="form-label">Jenis Surat *</label>
      <select class="form-select" id="fSuratJenis" onchange="Pages._onSuratJenisChange()">
        <option value="">-- Pilih jenis surat --</option>
        ${jenisEntries.map(([k, j]) => `<option value="${k}" ${existing?.jenis === k ? 'selected' : ''}>${j.icon} ${j.nama}</option>`).join('')}
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Nama KK / Pihak yang Dimohon *</label>
      <select class="form-select" id="fSuratKK">
        <option value="">-- Cari nama warga --</option>
        ${keluarga.map(k => `<option value="${k.id}" ${existing?.keluargaId === k.id ? 'selected' : ''}>${k.nama} (${k.rt})</option>`).join('')}
      </select>
      <div style="margin-top:4px;font-size:11px;color:var(--text2)">Atau isi nama manual jika bukan warga terdaftar:</div>
      <input type="text" class="form-input" id="fSuratNamaManual" placeholder="Nama pemohon (opsional)" value="${existing?.namaPemohon || ''}" style="margin-top:6px">
    </div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Tanggal Surat</label>
        <input type="date" class="form-input" id="fSuratTgl" value="${existing?.tanggal || new Date().toISOString().slice(0, 10)}">
      </div>
      <div class="form-group">
        <label class="form-label">No. Surat (auto-generate)</label>
        <input type="text" class="form-input" id="fSuratNoManual" placeholder="Diisi otomatis, bisa diubah" value="${existing?.nomorSurat || ''}">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Keperluan / Tujuan</label>
      <input type="text" class="form-input" id="fSuratKep" placeholder="Contoh: Pembuatan KTP, Beasiswa, dll" value="${existing?.keperluan || ''}">
    </div>
    <div id="suratExtraFields"></div>
    <button class="btn btn-primary btn-block mt-3" onclick="Pages._saveSurat('${suratId || ''}')">📜 Generate &amp; Cetak Surat</button>
  `);

    // Trigger extra fields if editing
    if (existing?.jenis) {
      setTimeout(() => Pages._onSuratJenisChange(existing), 0);
    }
  };

  Pages._onSuratJenisChange = function (existing) {
    const val = document.getElementById('fSuratJenis')?.value;
    const extra = document.getElementById('suratExtraFields');
    if (!extra) return;
    const ex = existing || {};

    // Auto-calculate nomor surat
    const tgl = new Date(document.getElementById('fSuratTgl')?.value || new Date());
    const tahun = DB.getSetting('tahun', 2026);
    const bulanRomawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    const bln = bulanRomawi[tgl.getMonth()];
    const rw = DB.getSetting('rw', '10');
    if (val) {
      const cnt = DB.getAll('surat').filter(s => s.jenis === val && s.tahun == tahun).length;
      const nomor = String(cnt + 1).padStart(3, '0');
      const kode = Pages._suratJenis[val]?.kode || 'SKT';
      const noEl = document.getElementById('fSuratNoManual');
      if (noEl && !noEl.value.trim()) noEl.value = `${kode}/${nomor}/RW${rw}/${bln}/${tahun}`;
    }

    // Field dinamis per jenis
    const fields = {
      sku: `
      <div class="form-row form-row-2">
        <div class="form-group"><label class="form-label">Nama Usaha</label><input type="text" class="form-input" id="fSuratUsaha" placeholder="Nama usaha" value="${ex.namaUsaha || ''}"></div>
        <div class="form-group"><label class="form-label">Jenis Usaha</label><input type="text" class="form-input" id="fSuratJenisUsaha" placeholder="Warung makan, dll" value="${ex.jenisUsaha || ''}"></div>
      </div>`,
      skm: `
      <div class="form-row form-row-2">
        <div class="form-group"><label class="form-label">Nama Almarhum/ah</label><input type="text" class="form-input" id="fSuratAlmarhum" placeholder="Nama lengkap" value="${ex.namaAlmarhum || ''}"></div>
        <div class="form-group"><label class="form-label">Tanggal Meninggal</label><input type="date" class="form-input" id="fSuratTglMeninggal" value="${ex.tanggalMeninggal || ''}"></div>
      </div>
      <div class="form-group"><label class="form-label">Penyebab Kematian</label><input type="text" class="form-input" id="fSuratPenyebab" placeholder="Sakit, dll" value="${ex.penyebabKematian || ''}"></div>`,
      skl: `
      <div class="form-row form-row-2">
        <div class="form-group"><label class="form-label">Nama Bayi</label><input type="text" class="form-input" id="fSuratNamaBayi" placeholder="Nama bayi" value="${ex.namaBayi || ''}"></div>
        <div class="form-group"><label class="form-label">Tanggal Lahir</label><input type="date" class="form-input" id="fSuratTglLahir" value="${ex.tanggalLahir || ''}"></div>
      </div>
      <div class="form-row form-row-2">
        <div class="form-group"><label class="form-label">Tempat Lahir</label><input type="text" class="form-input" id="fSuratTempatLahir" placeholder="Garut, dll" value="${ex.tempatLahir || ''}"></div>
        <div class="form-group"><label class="form-label">Jenis Kelamin</label><select class="form-select" id="fSuratJKBayi"><option value="L" ${ex.jenisKelaminBayi === 'L' ? 'selected' : ''}>Laki-laki</option><option value="P" ${ex.jenisKelaminBayi === 'P' ? 'selected' : ''}>Perempuan</option></select></div>
      </div>`,
      skpindah: `
      <div class="form-group"><label class="form-label">Alamat Tujuan Pindah</label><input type="text" class="form-input" id="fSuratAlamatTujuan" placeholder="Alamat baru" value="${ex.alamatTujuan || ''}"></div>
      <div class="form-group"><label class="form-label">Alasan Pindah</label><input type="text" class="form-input" id="fSuratAlasanPindah" placeholder="Mengikuti suami/istri, dll" value="${ex.alasanPindah || ''}"></div>`
    };
    extra.innerHTML = fields[val] || '';
  };

  Pages._saveSurat = function (editId) {
    const jenis = document.getElementById('fSuratJenis')?.value;
    const kkId = document.getElementById('fSuratKK')?.value;
    const namaManual = document.getElementById('fSuratNamaManual')?.value?.trim();
    const tanggal = document.getElementById('fSuratTgl')?.value;
    const keperluan = document.getElementById('fSuratKep')?.value?.trim();
    const nomorManual = document.getElementById('fSuratNoManual')?.value?.trim();
    if (!jenis) return App.toast('Pilih jenis surat terlebih dahulu', 'error');
    if (!kkId && !namaManual) return App.toast('Pilih nama warga atau isi nama manual', 'error');

    const tahun = DB.getSetting('tahun', 2026);
    const bulanRomawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    const tgl = new Date(tanggal || new Date());
    const bln = bulanRomawi[tgl.getMonth()];
    const th = tgl.getFullYear();
    const rw = DB.getSetting('rw', '10');

    // Auto-numbering jika belum ada nomor manual
    let nomorSurat = nomorManual;
    if (!nomorSurat) {
      const existing = DB.getAll('surat').filter(s => s.jenis === jenis && s.tahun == tahun);
      const nomor = String(existing.length + 1).padStart(3, '0');
      const kode = Pages._suratJenis[jenis]?.kode || 'SKT';
      nomorSurat = `${kode}/${nomor}/RW${rw}/${bln}/${th}`;
    }

    // Koleksi field extra per jenis
    const g = (id) => document.getElementById(id)?.value || '';
    const extra = {};
    if (jenis === 'sku') { extra.namaUsaha = g('fSuratUsaha'); extra.jenisUsaha = g('fSuratJenisUsaha'); }
    if (jenis === 'skm') { extra.namaAlmarhum = g('fSuratAlmarhum'); extra.tanggalMeninggal = g('fSuratTglMeninggal'); extra.penyebabKematian = g('fSuratPenyebab'); }
    if (jenis === 'skl') { extra.namaBayi = g('fSuratNamaBayi'); extra.tanggalLahir = g('fSuratTglLahir'); extra.tempatLahir = g('fSuratTempatLahir'); extra.jenisKelaminBayi = g('fSuratJKBayi'); }
    if (jenis === 'skpindah') { extra.alamatTujuan = g('fSuratAlamatTujuan'); extra.alasanPindah = g('fSuratAlasanPindah'); }

    const payload = { jenis, keluargaId: kkId || '', namaPemohon: namaManual, nomorSurat, tanggal: tanggal || tgl.toISOString().slice(0, 10), keperluan, tahun, cetakCount: 0, ...extra };

    // === RACI: tentukan status awal berdasarkan role ===
    const level = Auth.currentLevel();
    if (level === 'superadmin' || level === 'ketua_rw') {
      payload.status = 'disetujui_rw'; // langsung approved
    } else if (level === 'petugas_rt' || level === 'bendahara') {
      payload.status = 'disetujui_rt'; // sudah diverifikasi RT
    } else {
      payload.status = 'diajukan'; // warga: perlu approval
    }
    payload.createdBy = Auth.getSession()?.namaLengkap || 'System';
    payload.requestedByUserId = Auth.getSession()?.id || '';
    payload.createdAt = new Date().toISOString();

    let surat;
    if (editId) { DB.update('surat', editId, payload); surat = DB.getById('surat', editId); }
    else surat = DB.insert('surat', payload);

    App.closeModal();

    // Langsung cetak jika sudah disetujui RW
    if (payload.status === 'disetujui_rw') {
      Pages._cetakSurat(surat.id);
      App.toast('📜 Surat berhasil dibuat & siap cetak', 'success');
    } else {
      App.toast('📜 Surat berhasil diajukan, menunggu approval', 'info');
    }
    setTimeout(() => App.render('surat'), 500);
  };

  // === RACI Approval / Rejection ===
  Pages._approveSurat = function (suratId, newStatus) {
    const surat = DB.getById('surat', suratId);
    if (!surat) return App.toast('Surat tidak ditemukan', 'error');

    const session = Auth.getSession();
    DB.update('surat', suratId, {
      status: newStatus,
      [`${newStatus}_by`]: session?.namaLengkap || 'System',
      [`${newStatus}_at`]: new Date().toISOString()
    });

    const label = newStatus === 'disetujui_rt' ? 'RT' : 'RW';
    App.toast(`✅ Surat disetujui oleh ${label}`, 'success');
    App.render('surat');
  };

  Pages._rejectSurat = function (suratId, newStatus) {
    App.showModal('❌ Tolak Surat', `
      <div style="text-align:center;padding:8px 0 16px">
        <div style="font-size:2rem;margin-bottom:8px">❌</div>
        <p style="margin-bottom:16px;font-size:14px">Berikan alasan penolakan surat ini:</p>
      </div>
      <div class="form-group">
        <label class="form-label">Alasan Penolakan *</label>
        <textarea class="form-textarea" id="fRejectAlasan" placeholder="Contoh: Data tidak lengkap, NIK salah, dll"></textarea>
      </div>
      <button class="btn btn-danger btn-block mt-3" onclick="Pages._doRejectSurat('${suratId}','${newStatus}')">❌ Konfirmasi Penolakan</button>
    `);
  };

  Pages._doRejectSurat = function (suratId, newStatus) {
    const alasan = document.getElementById('fRejectAlasan')?.value?.trim();
    if (!alasan) return App.toast('Alasan penolakan wajib diisi', 'error');

    const session = Auth.getSession();
    DB.update('surat', suratId, {
      status: newStatus,
      alasanTolak: alasan,
      [`${newStatus}_by`]: session?.namaLengkap || 'System',
      [`${newStatus}_at`]: new Date().toISOString()
    });

    const label = newStatus === 'ditolak_rt' ? 'RT' : 'RW';
    App.toast(`❌ Surat ditolak oleh ${label}`, 'warning');
    App.closeModal();
    App.render('surat');
  };

  Pages._cetakSurat = function (suratId) {
    const surat = DB.getById('surat', suratId);
    if (!surat) return App.toast('Surat tidak ditemukan', 'error');
    const kk = surat.keluargaId ? DB.getById('keluarga', surat.keluargaId) : null;
    const rw = DB.getSetting('rw', '10');
    const kelurahan = DB.getSetting('kelurahan', 'Sukakarya');
    const kecamatan = DB.getSetting('kecamatan', 'Tarogong Kidul');
    const ketuaRW = DB.getSetting('ketuaRW', DB.getSetting('operator', 'Admin'));
    const j = Pages._suratJenis[surat.jenis] || {};
    const tgl = new Date(surat.tanggal || new Date());
    const tglStr = tgl.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    const rt = kk?.rt || '-';
    const namaPemohon = kk?.nama || surat.namaPemohon || '-';
    const nikPemohon = kk?.nik || '-';
    const alamatPemohon = kk?.alamat || '-';

    // Template isi surat per jenis
    const identitas = `
    <table style="margin:12px 0 16px 20px;border-collapse:collapse;width:90%">
      <tr><td style="width:38%;padding:3px 0">Nama</td><td>: <strong>${namaPemohon}</strong></td></tr>
      ${nikPemohon !== '-' ? `<tr><td style="padding:3px 0">NIK</td><td>: ${nikPemohon}</td></tr>` : ''}
      <tr><td style="padding:3px 0">Alamat</td><td>: ${alamatPemohon}</td></tr>
      <tr><td style="padding:3px 0">RT/RW</td><td>: ${rt} / RW ${rw}</td></tr>
    </table>`;

    const isiMap = {
      skd: `<p>Yang bertanda tangan di bawah ini, Ketua Rukun Warga ${rw} Kelurahan ${kelurahan}, Kecamatan ${kecamatan}, Kota Garut, menerangkan bahwa:</p>${identitas}<p>Adalah benar-benar warga yang <strong>berdomisili</strong> di wilayah ${rt} RW ${rw} Kelurahan ${kelurahan}.</p><p>Surat keterangan ini dibuat untuk keperluan: <strong>${surat.keperluan || '—'}</strong></p>`,
      sktm: `<p>Yang bertanda tangan di bawah ini, Ketua Rukun Warga ${rw} Kelurahan ${kelurahan}, Kecamatan ${kecamatan}, Kota Garut, menerangkan bahwa:</p>${identitas}<p>Adalah benar-benar termasuk dalam kategori <strong>warga kurang mampu / tidak mampu</strong> yang berdomisili di wilayah ${rt} RW ${rw} Kelurahan ${kelurahan}.</p><p>Surat ini dibuat untuk keperluan: <strong>${surat.keperluan || '—'}</strong></p>`,
      pengantar: `<p>Yang bertanda tangan di bawah ini, Ketua Rukun Warga ${rw} Kelurahan ${kelurahan}, menerangkan bahwa:</p>${identitas}<p>Adalah benar warga yang berdomisili di wilayah kami dan bermaksud untuk keperluan: <strong>${surat.keperluan || 'Pengurusan KTP/KK'}</strong> di Kelurahan ${kelurahan}.</p>`,
      sku: `<p>Yang bertanda tangan di bawah ini, Ketua Rukun Warga ${rw} Kelurahan ${kelurahan}, menerangkan bahwa:</p>${identitas}<table style="margin:0 0 12px 20px;border-collapse:collapse;width:90%"><tr><td style="width:38%;padding:3px 0">Nama Usaha</td><td>: <strong>${surat.namaUsaha || '-'}</strong></td></tr><tr><td style="padding:3px 0">Jenis Usaha</td><td>: ${surat.jenisUsaha || '-'}</td></tr></table><p>Adalah benar menjalankan usaha di wilayah ${rt} RW ${rw} Kelurahan ${kelurahan}. Keperluan: <strong>${surat.keperluan || '—'}</strong></p>`,
      skck: `<p>Yang bertanda tangan di bawah ini, Ketua Rukun Warga ${rw} Kelurahan ${kelurahan}, menerangkan bahwa:</p>${identitas}<p>Berdasarkan pengetahuan kami, yang bersangkutan adalah warga yang <strong>berkelakuan baik</strong> dan tidak pernah tersangkut tindak kriminal selama berdomisili di wilayah ${rt} RW ${rw} Kelurahan ${kelurahan}.</p><p>Surat pengantar ini dibuat untuk keperluan: <strong>Pengurusan SKCK di Kepolisian. ${surat.keperluan || ''}</strong></p>`,
      skl: `<p>Yang bertanda tangan di bawah ini, Ketua Rukun Warga ${rw} Kelurahan ${kelurahan}, menerangkan bahwa di wilayah kami telah lahir seorang bayi dengan keterangan:</p><table style="margin:12px 0 16px 20px;border-collapse:collapse;width:90%"><tr><td style="width:38%;padding:3px 0">Nama Bayi</td><td>: <strong>${surat.namaBayi || '-'}</strong></td></tr><tr><td style="padding:3px 0">Jenis Kelamin</td><td>: ${surat.jenisKelaminBayi === 'L' ? 'Laki-laki' : 'Perempuan'}</td></tr><tr><td style="padding:3px 0">Tempat, Tgl Lahir</td><td>: ${surat.tempatLahir || '-'}, ${surat.tanggalLahir ? new Date(surat.tanggalLahir).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-'}</td></tr><tr><td style="padding:3px 0">Orang Tua (KK)</td><td>: ${namaPemohon} — ${alamatPemohon}</td></tr></table><p>Keperluan: <strong>${surat.keperluan || 'Pengurusan Akte Kelahiran'}</strong></p>`,
      skm: `<p>Yang bertanda tangan di bawah ini, Ketua Rukun Warga ${rw} Kelurahan ${kelurahan}, menerangkan bahwa telah meninggal dunia:</p><table style="margin:12px 0 16px 20px;border-collapse:collapse;width:90%"><tr><td style="width:38%;padding:3px 0">Nama Almarhum/ah</td><td>: <strong>${surat.namaAlmarhum || '-'}</strong></td></tr><tr><td style="padding:3px 0">Tanggal Meninggal</td><td>: ${surat.tanggalMeninggal ? new Date(surat.tanggalMeninggal).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-'}</td></tr><tr><td style="padding:3px 0">Penyebab Kematian</td><td>: ${surat.penyebabKematian || '-'}</td></tr><tr><td style="padding:3px 0">Anggota KK Dari</td><td>: ${namaPemohon} — ${alamatPemohon}</td></tr></table><p>Keperluan: <strong>${surat.keperluan || 'Pengurusan Administrasi Kependudukan'}</strong></p>`,
      skpindah: `<p>Yang bertanda tangan di bawah ini, Ketua Rukun Warga ${rw} Kelurahan ${kelurahan}, menerangkan bahwa:</p>${identitas}<p>Yang bersangkutan bermaksud <strong>pindah domisili</strong> ke alamat: <strong>${surat.alamatTujuan || '-'}</strong></p><p>Alasan pindah: ${surat.alasanPindah || '-'}. Keperluan: <strong>${surat.keperluan || 'Pengurusan Surat Pindah'}</strong></p>`,
      skbm: `<p>Yang bertanda tangan di bawah ini, Ketua Rukun Warga ${rw} Kelurahan ${kelurahan}, menerangkan bahwa:</p>${identitas}<p>Berdasarkan keterangan dan pemeriksaan kami, yang bersangkutan adalah <strong>belum menikah</strong> hingga surat ini dibuat.</p><p>Keperluan: <strong>${surat.keperluan || '—'}</strong></p>`,
      skkb: `<p>Yang bertanda tangan di bawah ini, Ketua Rukun Warga ${rw} Kelurahan ${kelurahan}, menerangkan bahwa:</p>${identitas}<p>Adalah warga yang dikenal <strong>berkelakuan baik</strong>, sopan santun, dan tidak pernah tersangkut tindak pidana atau pelanggaran hukum selama berdomisili di wilayah kami.</p><p>Keperluan: <strong>${surat.keperluan || '—'}</strong></p>`
    };

    const isiSurat = isiMap[surat.jenis] || `<p>Menerangkan bahwa ${namaPemohon} adalah warga ${rt} RW ${rw} Kelurahan ${kelurahan}.</p>`;

    const html = `
    <div id="suratPrintArea" style="font-family:'Times New Roman',serif;font-size:12pt;line-height:1.7;color:#000;background:#fff;padding:32px 40px;max-width:720px;margin:0 auto">
      <!-- Kop Surat -->
      <div style="text-align:center;border-bottom:3px double #000;padding-bottom:12px;margin-bottom:16px">
        <div style="font-size:10pt;font-weight:700;text-transform:uppercase;letter-spacing:1px">RUKUN WARGA ${rw}</div>
        <div style="font-size:9pt">Kelurahan ${kelurahan}, Kecamatan ${kecamatan}</div>
        <div style="font-size:9pt">Kota Garut, Provinsi Jawa Barat</div>
      </div>
      <!-- Judul -->
      <div style="text-align:center;margin:16px 0">
        <div style="font-size:14pt;font-weight:700;text-transform:uppercase;text-decoration:underline">${(j.nama || 'SURAT KETERANGAN').toUpperCase()}</div>
        <div style="font-size:10pt;margin-top:4px">Nomor: <strong>${surat.nomorSurat}</strong></div>
      </div>
      <!-- Isi -->
      <div style="margin:20px 0;text-align:justify">${isiSurat}</div>
      <!-- Penutup -->
      <p style="margin:16px 0">Demikian surat keterangan ini dibuat dengan sebenar-benarnya, untuk dapat dipergunakan sebagaimana mestinya.</p>
      <!-- Tanda tangan -->
      <div style="display:flex;justify-content:flex-end;margin-top:32px">
        <div style="text-align:center;min-width:200px">
          <div>Garut, ${tglStr}</div>
          <div>Ketua RW ${rw}</div>
          <div style="margin:56px 0 4px">&nbsp;</div>
          <div style="font-weight:700;text-decoration:underline">${ketuaRW}</div>
        </div>
      </div>
    </div>
    <div style="text-align:center;margin-top:16px" class="no-print">
      <button class="btn btn-primary" onclick="window.print()">🖨️ Cetak Surat</button>
      <button class="btn" onclick="App.closeModal()">✕ Tutup</button>
    </div>`;

    // Update cetak count
    DB.update('surat', suratId, { cetakCount: (surat.cetakCount || 1) + 1 });
    App.showModal(`${j.icon || '📜'} ${j.nama}`, html);
  };

})();
