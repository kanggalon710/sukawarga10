/* Pages Kegiatan — Modul Kegiatan RW/RT */
(function () {
    window.Pages = window.Pages || {};
    const Pages = window.Pages;

    Pages._kegiatanFilter = '';

    Pages.kegiatan = function () {
        const tahun = DB.getSetting('tahun', 2026);
        let data = DB.getAll('kegiatan').sort((a, b) => (a.tanggal || '').localeCompare(b.tanggal || ''));
        const filter = Pages._kegiatanFilter;
        if (filter) data = data.filter(k => k.jenis === filter);

        const jenisOpts = ['Rapat', 'Gotong Royong', 'Posyandu', 'Pengajian', 'Olahraga', 'Sosial', 'HUT/Peringatan', 'Lainnya'];
        const upcoming = data.filter(k => k.tanggal >= new Date().toISOString().slice(0, 10));

        return `
  <div class="toolbar">
    <div class="toolbar-left">
      <select class="filter-select" onchange="Pages._kegiatanFilter=this.value;App.render('kegiatan')">
        <option value="">Semua Jenis</option>
        ${jenisOpts.map(j => `<option value="${j}" ${filter === j ? 'selected' : ''}>${j}</option>`).join('')}
      </select>
      <span class="badge badge-info">${data.length} kegiatan</span>
      ${upcoming.length ? `<span class="badge badge-success">${upcoming.length} mendatang</span>` : ''}
    </div>
    <div class="toolbar-right"><button class="btn btn-primary" onclick="Pages.showFormKegiatan()">➕ Tambah Kegiatan</button></div>
  </div>

  ${upcoming.length ? `<div class="alert-cards-row">${upcoming.slice(0, 3).map(k => `
    <div class="alert-card alert-info">
      <div class="alert-card-icon">📅</div>
      <div><strong>${k.nama}</strong><small>${k.tanggal} · ${k.waktu || ''} · ${k.tempat || ''}</small></div>
    </div>`).join('')}</div>` : ''}

  <div class="card"><div class="data-table-wrapper"><table class="data-table"><thead><tr><th>No</th><th>Tanggal</th><th>Nama Kegiatan</th><th>Jenis</th><th>Waktu</th><th>Tempat</th><th>PIC</th><th>Aksi</th></tr></thead><tbody>
  ${data.length ? data.map((k, i) => {
            const isPast = k.tanggal < new Date().toISOString().slice(0, 10);
            return `<tr style="${isPast ? 'opacity:0.6' : ''}"><td>${i + 1}</td><td>${k.tanggal || '-'}</td><td><strong>${k.nama || '-'}</strong>${k.deskripsi ? `<br><small style="color:var(--text3)">${k.deskripsi.substring(0, 60)}${k.deskripsi.length > 60 ? '...' : ''}</small>` : ''}</td><td><span class="badge badge-info">${k.jenis || '-'}</span></td><td>${k.waktu || '-'}</td><td>${k.tempat || '-'}</td><td>${k.pic || '-'}</td><td style="white-space:nowrap"><button class="btn btn-sm btn-outline" onclick="Pages.showFormKegiatan('${k.id}')" title="Edit">✏️</button> <button class="btn btn-sm btn-danger" onclick="App.confirmDelete('kegiatan','${k.id}','Kegiatan ${(k.nama || '').replace(/'/g, "\\'")}')" title="Hapus">🗑️</button></td></tr>`;
        }).join('')
                : '<tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon">📅</div><div class="empty-state-title">Belum ada kegiatan</div><div class="empty-state-sub">Tambah kegiatan RW/RT pertama</div></div></td></tr>'}
  </tbody></table></div></div>`;
    };

    Pages.showFormKegiatan = function (id) {
        const k = id ? DB.getById('kegiatan', id) : {};
        const isEdit = !!id;
        const jenisOpts = ['Rapat', 'Gotong Royong', 'Posyandu', 'Pengajian', 'Olahraga', 'Sosial', 'HUT/Peringatan', 'Lainnya'];
        App.showModal(isEdit ? '✏️ Edit Kegiatan' : '➕ Tambah Kegiatan', `
    <div class="form-group"><label class="form-label">Nama Kegiatan *</label><input type="text" class="form-input" id="fKgtNama" value="${k.nama || ''}" placeholder="Nama kegiatan"></div>
    <div class="form-row form-row-2">
      <div class="form-group"><label class="form-label">Tanggal *</label><input type="date" class="form-input" id="fKgtTanggal" value="${k.tanggal || new Date().toISOString().slice(0, 10)}"></div>
      <div class="form-group"><label class="form-label">Waktu</label><input type="time" class="form-input" id="fKgtWaktu" value="${k.waktu || ''}"></div>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group"><label class="form-label">Jenis</label><select class="form-select" id="fKgtJenis">${jenisOpts.map(j => `<option ${k.jenis === j ? 'selected' : ''}>${j}</option>`).join('')}</select></div>
      <div class="form-group"><label class="form-label">Tempat</label><input type="text" class="form-input" id="fKgtTempat" value="${k.tempat || ''}" placeholder="Lokasi kegiatan"></div>
    </div>
    <div class="form-group"><label class="form-label">PIC / Penanggung Jawab</label><input type="text" class="form-input" id="fKgtPIC" value="${k.pic || ''}"></div>
    <div class="form-group"><label class="form-label">Deskripsi</label><textarea class="form-textarea" id="fKgtDeskripsi">${k.deskripsi || ''}</textarea></div>
    <button class="btn btn-primary btn-block mt-3" onclick="Pages.saveKegiatan('${id || ''}')">💾 Simpan</button>
  `);
    };

    Pages.saveKegiatan = function (id) {
        const nama = document.getElementById('fKgtNama').value.trim();
        if (!nama) return App.toast('Nama kegiatan wajib', 'error');
        const data = {
            nama,
            tanggal: document.getElementById('fKgtTanggal').value,
            waktu: document.getElementById('fKgtWaktu').value,
            jenis: document.getElementById('fKgtJenis').value,
            tempat: document.getElementById('fKgtTempat').value.trim(),
            pic: document.getElementById('fKgtPIC').value.trim(),
            deskripsi: document.getElementById('fKgtDeskripsi').value.trim(),
            tahun: DB.getSetting('tahun', 2026)
        };
        if (id) { DB.update('kegiatan', id, data); App.toast('✅ Kegiatan diperbarui', 'success'); }
        else { DB.insert('kegiatan', data); App.toast('✅ Kegiatan ditambahkan', 'success'); }
        App.closeModal(); App.render('kegiatan');
    };
})();
