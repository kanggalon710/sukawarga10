/* Pages — UMKM Warga */
(function () {
  window.Pages = window.Pages || {};
  const Pages = window.Pages;

  Pages.umkm = function () {
    const filterRT = Pages._umkmRT || '';
    const filterJenis = Pages._umkmJenis || '';
    const filterStatus = Pages._umkmStatus || '';
    const q = Pages._umkmQ || '';

    const keluarga = DB.getAll('keluarga');
    let list = DB.getAll('umkm');

    // Join with keluarga
    list = list.map(u => ({ ...u, _kk: keluarga.find(k => k.id === u.keluargaId) }));

    // Filter
    if (filterRT) list = list.filter(u => u._kk?.rt === filterRT);
    if (filterJenis) list = list.filter(u => u.jenis === filterJenis);
    if (filterStatus) list = list.filter(u => u.status === filterStatus);
    if (q) list = list.filter(u => u.namaUsaha?.toLowerCase().includes(q.toLowerCase()) || u._kk?.nama?.toLowerCase().includes(q.toLowerCase()));

    const allRT = [...new Set(keluarga.map(k => k.rt))].sort();
    const allJenis = [...new Set(DB.getAll('umkm').map(u => u.jenis).filter(Boolean))].sort();
    const totalAktif = list.filter(u => u.status === 'aktif' || !u.status).length;
    const totalMusiman = list.filter(u => u.status === 'musiman').length;

    return `
  <div class="toolbar">
    <div class="toolbar-left">
      <input type="text" class="form-input" style="max-width:200px;height:36px" placeholder="🔍 Cari usaha/warga..." value="${q}" oninput="Pages._umkmQ=this.value;App.render('umkm')">
    </div>
    <div class="toolbar-right">
      <button class="btn" onclick="Pages._exportUMKMCSV()">📥 Export CSV</button>
      <button class="btn btn-primary" onclick="Pages._showFormUMKM()">➕ Daftarkan UMKM</button>
    </div>
  </div>

  <div class="stats-row" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">
    <div class="stat-card green"><div class="stat-accent"></div><div class="stat-icon-box">🏪</div><div class="stat-label">Total UMKM</div><div class="stat-value">${list.length}</div><div class="stat-sub">${totalAktif} aktif</div></div>
    <div class="stat-card gold"><div class="stat-accent"></div><div class="stat-icon-box">🌙</div><div class="stat-label">Musiman</div><div class="stat-value">${totalMusiman}</div><div class="stat-sub">tidak rutin</div></div>
    <div class="stat-card blue"><div class="stat-accent"></div><div class="stat-icon-box">📍</div><div class="stat-label">RT Aktif</div><div class="stat-value">${new Set(list.map(u => u._kk?.rt).filter(Boolean)).size}</div><div class="stat-sub">dari ${allRT.length} RT</div></div>
  </div>

  <div class="card">
    <div class="filter-row" style="margin-bottom:12px">
      <span class="filter-chip ${!filterRT ? 'active' : ''}" onclick="Pages._umkmRT='';App.render('umkm')">Semua RT</span>
      ${allRT.map(rt => `<span class="filter-chip ${filterRT === rt ? 'active' : ''}" onclick="Pages._umkmRT='${rt}';App.render('umkm')">${rt}</span>`).join('')}
      <span style="margin-left:8px"></span>
      <span class="filter-chip ${!filterStatus ? 'active' : ''}" onclick="Pages._umkmStatus='';App.render('umkm')">Semua Status</span>
      <span class="filter-chip ${filterStatus === 'aktif' ? 'active' : ''}" onclick="Pages._umkmStatus='aktif';App.render('umkm')">✅ Aktif</span>
      <span class="filter-chip ${filterStatus === 'musiman' ? 'active' : ''}" onclick="Pages._umkmStatus='musiman';App.render('umkm')">🌙 Musiman</span>
      <span class="filter-chip ${filterStatus === 'tidak_aktif' ? 'active' : ''}" onclick="Pages._umkmStatus='tidak_aktif';App.render('umkm')">❌ Tidak Aktif</span>
    </div>

    <div class="data-table-wrapper">
      <table class="data-table">
        <thead><tr><th>No</th><th>Nama Usaha</th><th>Pemilik</th><th>RT</th><th>Jenis</th><th>Omzet</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          ${list.length ? list.map((u, i) => {
      const statusBadge = u.status === 'aktif' || !u.status ? '<span class="badge badge-green">✅ Aktif</span>'
        : u.status === 'musiman' ? '<span class="badge badge-gold">🌙 Musiman</span>'
          : '<span class="badge badge-gray">❌ Tidak Aktif</span>';
      return `<tr>
              <td>${i + 1}</td>
              <td><strong>${u.namaUsaha || '-'}</strong>${u.catatan ? `<div style="font-size:11px;color:var(--text3)">${u.catatan}</div>` : ''}</td>
              <td>${u._kk?.nama || '-'}</td>
              <td><span class="badge badge-blue" style="font-size:10px">${u._kk?.rt || '-'}</span></td>
              <td>${u.jenis || '-'}</td>
              <td style="font-family:'Space Mono',monospace;font-size:12px">${u.omzetRange || '-'}</td>
              <td>${statusBadge}</td>
              <td>
                <button class="btn" style="padding:4px 8px;font-size:12px" onclick="Pages._showFormUMKM('${u.id}')">✏️</button>
                <button class="btn btn-danger" style="padding:4px 8px;font-size:12px" onclick="App.confirmDelete('umkm','${u.id}','UMKM ${(u.namaUsaha || '').replace(/'/g, "\\'")}')">🗑️</button>
              </td>
            </tr>`;
    }).join('')
        : '<tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon">🏪</div><div class="empty-state-title">Belum ada data UMKM</div><div class="empty-state-subtitle">Daftarkan UMKM warga dengan klik tombol di atas</div></div></td></tr>'}
        </tbody>
      </table>
    </div>
  </div>`;
  };

  Pages._showFormUMKM = function (id) {
    const existing = id ? DB.getById('umkm', id) : null;
    const keluarga = DB.getAll('keluarga').filter(k => (k.status || 'aktif') === 'aktif');
    const chk = (arr, val) => (arr || []).includes(val) ? 'checked' : '';
    const platformOpts = ['WhatsApp Business', 'Instagram', 'TikTok Shop', 'Shopee', 'Tokopedia', 'Facebook', 'GoFood/GrabFood'];
    const perizinanOpts = ['NIB (Nomor Induk Berusaha)', 'IUMK', 'SIUP', 'Sertifikat Halal', 'PIRT', 'Merek Terdaftar'];

    App.showModal(id ? '✏️ Edit UMKM' : '🏪 Daftarkan UMKM Baru', `
    <div class="form-group">
      <label class="form-label">Pemilik (KK) *</label>
      <select class="form-select" id="fUmkmKK">
        <option value="">-- Pilih warga --</option>
        ${keluarga.map(k => `<option value="${k.id}" ${existing?.keluargaId === k.id ? 'selected' : ''}>${k.nama} (${k.rt})</option>`).join('')}
      </select>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Nama Usaha *</label>
        <input type="text" class="form-input" id="fUmkmNama" value="${existing?.namaUsaha || ''}" placeholder="Contoh: Warung Mang Ujang">
      </div>
      <div class="form-group">
        <label class="form-label">Jenis Usaha *</label>
        <input type="text" class="form-input" id="fUmkmJenis" value="${existing?.jenis || ''}" placeholder="Warung, Jahit, Bengkel, dll" list="jenisUsahaList">
        <datalist id="jenisUsahaList">
          <option>Warung makan</option><option>Warung sembako</option><option>Jahit/konveksi</option>
          <option>Bengkel</option><option>Salon/barbershop</option><option>Pertanian</option>
          <option>Ternak</option><option>Jasa angkut</option><option>Online shop</option><option>Kuliner</option><option>Laundry</option>
        </datalist>
      </div>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Estimasi Omzet/Bulan</label>
        <select class="form-select" id="fUmkmOmzet">
          <option value="">-- Pilih kisaran --</option>
          ${['< Rp 1 Juta', 'Rp 1 - 5 Juta', 'Rp 5 - 10 Juta', 'Rp 10 - 50 Juta', '> Rp 50 Juta'].map(o => `<option ${existing?.omzetRange === o ? 'selected' : ''}>${o}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Status Usaha</label>
        <select class="form-select" id="fUmkmStatus">
          <option value="aktif" ${(!existing?.status || existing?.status === 'aktif') ? 'selected' : ''}>✅ Aktif</option>
          <option value="musiman" ${existing?.status === 'musiman' ? 'selected' : ''}>🌙 Musiman</option>
          <option value="tidak_aktif" ${existing?.status === 'tidak_aktif' ? 'selected' : ''}>❌ Tidak Aktif</option>
        </select>
      </div>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">No. Telepon Usaha</label>
        <input type="tel" class="form-input" id="fUmkmTelepon" value="${existing?.telepon || ''}" placeholder="0812xxxx / WA">
      </div>
      <div class="form-group">
        <label class="form-label">Tahun Berdiri</label>
        <input type="number" class="form-input" id="fUmkmTahunBerdiri" value="${existing?.tahunBerdiri || ''}" placeholder="2020" min="1900" max="${new Date().getFullYear()}">
      </div>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Jumlah Karyawan</label>
        <input type="number" class="form-input" id="fUmkmKaryawan" value="${existing?.jumlahKaryawan || ''}" placeholder="0 = sendiri" min="0">
      </div>
      <div class="form-group">
        <label class="form-label">NIB / No. Izin Usaha</label>
        <input type="text" class="form-input" id="fUmkmNIB" value="${existing?.nib || ''}" placeholder="Nomor NIB/IUMK">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Lokasi Usaha</label>
      <input type="text" class="form-input" id="fUmkmLokasi" value="${existing?.lokasiUsaha || ''}" placeholder="Alamat atau deskripsi lokasi">
    </div>
    <div class="form-group">
      <label class="form-label">Media Sosial</label>
      <input type="text" class="form-input" id="fUmkmMedsos" value="${existing?.mediaSosial || ''}" placeholder="@instagram, fb.com/toko, dll">
    </div>
    <div class="form-group">
      <label class="form-label">Platform Online</label>
      <div class="tag-checkbox-group">
        ${platformOpts.map(o => `<label class="tag-checkbox"><input type="checkbox" class="fUmkmPlatform" value="${o}" ${chk(existing?.platform, o)} style="accent-color:var(--hijau)">${o}</label>`).join('')}
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Legalitas / Perizinan</label>
      <div class="tag-checkbox-group">
        ${perizinanOpts.map(o => `<label class="tag-checkbox"><input type="checkbox" class="fUmkmPerizinan" value="${o}" ${chk(existing?.perizinan, o)} style="accent-color:var(--hijau)">${o}</label>`).join('')}
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Catatan</label>
      <input type="text" class="form-input" id="fUmkmCatatan" value="${existing?.catatan || ''}" placeholder="Produk unggulan, info lain">
    </div>
    <button class="btn btn-primary btn-block mt-3" onclick="Pages._saveUMKM('${id || ''}')">💾 Simpan</button>
  `);
  };

  Pages._saveUMKM = function (id) {
    const kkId = document.getElementById('fUmkmKK')?.value;
    const namaUsaha = document.getElementById('fUmkmNama')?.value?.trim();
    const jenis = document.getElementById('fUmkmJenis')?.value?.trim();
    if (!kkId || !namaUsaha) return App.toast('Pilih pemilik dan isi nama usaha', 'error');
    const multiChk = (cls) => [...document.querySelectorAll('.' + cls + ':checked')].map(c => c.value);

    const data = {
      keluargaId: kkId, namaUsaha, jenis,
      omzetRange: document.getElementById('fUmkmOmzet')?.value || '',
      status: document.getElementById('fUmkmStatus')?.value || 'aktif',
      telepon: document.getElementById('fUmkmTelepon')?.value?.trim() || '',
      tahunBerdiri: parseInt(document.getElementById('fUmkmTahunBerdiri')?.value) || '',
      jumlahKaryawan: parseInt(document.getElementById('fUmkmKaryawan')?.value) || 0,
      nib: document.getElementById('fUmkmNIB')?.value?.trim() || '',
      lokasiUsaha: document.getElementById('fUmkmLokasi')?.value?.trim() || '',
      mediaSosial: document.getElementById('fUmkmMedsos')?.value?.trim() || '',
      platform: multiChk('fUmkmPlatform'),
      perizinan: multiChk('fUmkmPerizinan'),
      catatan: document.getElementById('fUmkmCatatan')?.value?.trim() || ''
    };

    if (id) { DB.update('umkm', id, data); App.toast('✅ Data UMKM diperbarui', 'success'); }
    else { DB.insert('umkm', data); App.toast('✅ UMKM berhasil didaftarkan', 'success'); }
    App.closeModal();
    App.render('umkm');
  };

  Pages._exportUMKMCSV = function () {
    const keluarga = DB.getAll('keluarga');
    const list = DB.getAll('umkm').map(u => {
      const kk = keluarga.find(k => k.id === u.keluargaId);
      return {
        'Nama Usaha': u.namaUsaha || '',
        'Pemilik': kk?.nama || '',
        'RT': kk?.rt || '',
        'Jenis': u.jenis || '',
        'Omzet/Bulan': u.omzetRange || '',
        'Telepon': u.telepon || '',
        'Tahun Berdiri': u.tahunBerdiri || '',
        'Jumlah Karyawan': u.jumlahKaryawan || 0,
        'NIB/No. Izin': u.nib || '',
        'Lokasi': u.lokasiUsaha || '',
        'Media Sosial': u.mediaSosial || '',
        'Platform Online': (u.platform || []).join('; '),
        'Perizinan': (u.perizinan || []).join('; '),
        'Status': u.status || 'aktif',
        'Catatan': u.catatan || ''
      };
    });
    DB.exportCSV(list, `umkm-rw10-${new Date().getFullYear()}.csv`);
    App.toast('📥 CSV UMKM diunduh', 'success');
  };

})();
