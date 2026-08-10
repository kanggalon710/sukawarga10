/* Pages — Manajemen Akun (hanya superadmin & ketua_rw) */
(function () {
  window.Pages = window.Pages || {};
  const Pages = window.Pages;

  Pages.users = function () {
    // Guard: hanya superadmin dan ketua_rw
    if (!Auth.can('canManageUsers') && Auth.currentLevel() !== 'ketua_rw') {
      return `<div class="empty-state"><div class="empty-state-icon">🔒</div><div class="empty-state-title">Akses Ditolak</div><div class="empty-state-subtitle">Anda tidak memiliki izin untuk mengakses halaman ini</div></div>`;
    }

    const users = DB.getAll('users').sort((a, b) => {
      const order = { superadmin: 0, ketua_rw: 1, bendahara: 2, petugas_rt: 3, warga: 4 };
      return (order[a.level] ?? 5) - (order[b.level] ?? 5);
    });
    const session = Auth.getSession();
    const levels = Auth.getLevels();

    // Stats per level
    const levelCounts = {};
    users.forEach(u => { levelCounts[u.level] = (levelCounts[u.level] || 0) + 1; });

    return `
  <div class="toolbar">
    <div class="toolbar-left">
      <span style="font-size:13px;color:var(--text2)">👥 ${users.length} akun terdaftar</span>
    </div>
    <div class="toolbar-right">
      <button class="btn btn-primary" onclick="Pages._showFormUser()">➕ Tambah Akun</button>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:20px">
    ${Object.entries(levels).map(([kode, info]) => {
      const cnt = levelCounts[kode] || 0;
      return `<div class="stat-card" style="cursor:default">
          <div class="stat-icon-box">${info.icon}</div>
          <div class="stat-label">${info.label}</div>
          <div class="stat-value" style="color:${info.color}">${cnt}</div>
        </div>`;
    }).join('')}
  </div>

  <div class="card">
    <div class="data-table-wrapper">
      <table class="data-table">
        <thead><tr><th>Username</th><th>Nama Lengkap</th><th>Level</th><th>RT</th><th>Status</th><th>Terakhir Login</th><th>Aksi</th></tr></thead>
        <tbody>
          ${users.length ? users.map(u => {
      const lvl = levels[u.level] || { label: u.level, icon: '👤', color: '#999' };
      const isMe = u.id === session?.id;
      const lastLogin = u.lastLogin ? new Date(u.lastLogin).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : 'Belum pernah';
      return `<tr ${!u.aktif ? 'style="opacity:0.5"' : ''}>
              <td class="td-mono"><strong>${u.username}</strong>${isMe ? ' <span class="badge badge-green" style="font-size:9px">Anda</span>' : ''}</td>
              <td>${u.namaLengkap || '-'}</td>
              <td><span class="badge" style="background:${lvl.color}22;color:${lvl.color};border:1px solid ${lvl.color}33">${lvl.icon} ${lvl.label}</span></td>
              <td>${u.rt || '<span style="color:var(--text3)">Semua RT</span>'}</td>
              <td>${u.aktif !== false ? '<span class="badge badge-green">✅ Aktif</span>' : '<span class="badge badge-gray">❌ Nonaktif</span>'}</td>
              <td style="font-size:11px;color:var(--text2)">${lastLogin}</td>
              <td>
                <button class="btn" style="padding:4px 8px;font-size:12px" onclick="Pages._showFormUser('${u.id}')">✏️</button>
                <button class="btn" style="padding:4px 8px;font-size:12px;background:var(--kuning-muda)" onclick="Pages._resetPIN('${u.id}','${u.username}')">🔑 PIN</button>
                ${!isMe ? `<button class="btn ${u.aktif !== false ? 'btn-warning' : 'btn-success'}" style="padding:4px 8px;font-size:12px" onclick="Pages._toggleAktif('${u.id}',${u.aktif !== false})">
                  ${u.aktif !== false ? '⏸️' : '▶️'}
                </button>` : ''}
              </td>
            </tr>`;
    }).join('')
        : '<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">👥</div><div class="empty-state-title">Belum ada akun</div></div></td></tr>'}
        </tbody>
      </table>
    </div>
  </div>

  <!-- Panduan Level -->
  <div class="card" style="margin-top:16px">
    <div style="padding:16px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
         <h3 style="font-size:14px;font-weight:700;margin:0">🔐 Konfigurasi Hak Akses (RBAC)</h3>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px">
        ${Object.values(levels).map(info => `
          <div style="border:1px solid ${info.color}33;border-radius:10px;padding:12px;background:${info.color}08;position:relative;">
            ${Auth.can('canSettings') ? `<button class="btn btn-sm btn-outline" style="position:absolute;top:10px;right:10px;padding:2px 8px;font-size:10px;border-color:${info.color}55;color:${info.color}" onclick="Pages._editRole('${info.id}')">✏️ Edit</button>` : ''}
            <div style="font-weight:700;font-size:13px;color:${info.color};margin-bottom:6px;padding-right:50px">${info.icon} ${info.label}</div>
            <div style="font-size:11px;color:var(--text2);line-height:1.7">
              ${info.canEdit ? '✅ Edit data<br>' : '❌ Hanya lihat<br>'}
              ${info.canDelete ? '✅ Hapus data<br>' : '❌ Tidak bisa hapus<br>'}
              ${info.canSettings ? '✅ Pengaturan<br>' : ''}
              ${info.canManageUsers ? '✅ Kelola akun<br>' : ''}
              ${info.rtFilter ? '⚠️ Filter RT sendiri<br>' : ''}
              Menu: ${(info.menus || []).length} halaman terpilih
            </div>
          </div>`).join('')}
      </div>
    </div>
  </div>`;
  };

  Pages._showFormUser = function (id) {
    const existing = id ? DB.getById('users', id) : null;
    const levels = Auth.getLevels();
    const rtList = ['RT 01', 'RT 02', 'RT 03'];

    App.showModal(id ? '✏️ Edit Akun' : '➕ Tambah Akun Baru', `
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Username *</label>
        <input type="text" class="form-input" id="fUserUsername" value="${existing?.username || ''}" placeholder="Huruf kecil, tanpa spasi" autocapitalize="none"${id ? ' readonly style="background:var(--bg2)"' : ''}>
      </div>
      <div class="form-group">
        <label class="form-label">Nama Lengkap *</label>
        <input type="text" class="form-input" id="fUserNama" value="${existing?.namaLengkap || ''}" placeholder="Nama lengkap pengguna">
      </div>
    </div>
    ${!id ? `
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">PIN (6 Digit) *</label>
        <input type="password" class="form-input" id="fUserPIN" maxlength="6" inputmode="numeric" placeholder="6 digit angka">
      </div>
      <div class="form-group">
        <label class="form-label">Konfirmasi PIN *</label>
        <input type="password" class="form-input" id="fUserPINConfirm" maxlength="6" inputmode="numeric" placeholder="Ulangi PIN">
      </div>
    </div>` : ''}
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Level / Jabatan *</label>
        <select class="form-select" id="fUserLevel" onchange="Pages._onUserLevelChange()">
          ${Object.entries(levels).map(([kode, info]) => `<option value="${kode}" ${existing?.level === kode ? 'selected' : ''}>${info.icon} ${info.label}</option>`).join('')}
        </select>
      </div>
      <div class="form-group" id="fUserRTGroup" style="${existing?.level === 'petugas_rt' ? '' : 'display:none'}">
        <label class="form-label">RT (untuk Petugas RT)</label>
        <select class="form-select" id="fUserRT">
          <option value="">-- Pilih RT --</option>
          ${rtList.map(rt => `<option value="${rt}" ${existing?.rt === rt ? 'selected' : ''}>${rt}</option>`).join('')}
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Status Akun</label>
      <select class="form-select" id="fUserAktif">
        <option value="1" ${existing?.aktif !== false ? 'selected' : ''}>✅ Aktif</option>
        <option value="0" ${existing?.aktif === false ? 'selected' : ''}>❌ Nonaktif</option>
      </select>
    </div>
    ${id ? `<div style="background:var(--bg2);border-radius:10px;padding:12px;font-size:12px;color:var(--text2);margin-bottom:12px">
      💡 Untuk mengganti PIN, gunakan tombol <strong>🔑 PIN</strong> di tabel setelah simpan.
    </div>` : ''}
    <button class="btn btn-primary btn-block mt-3" onclick="Pages._saveUser('${id || ''}')">💾 Simpan Akun</button>
  `);
  };

  Pages._onUserLevelChange = function () {
    const level = document.getElementById('fUserLevel')?.value;
    const rtGroup = document.getElementById('fUserRTGroup');
    if (rtGroup) rtGroup.style.display = level === 'petugas_rt' ? '' : 'none';
  };

  // === Edit Role (RBAC) ===
  Pages._editRole = function (roleId) {
    const role = DB.getById('roles', roleId);
    if (!role) return App.toast('Role tidak ditemukan', 'error');

    const allMenus = [
      { id: 'dashboard', name: 'Dashboard' }, { id: 'pendataan', name: 'Pendataan Warga' },
      { id: 'sampah', name: 'Iuran Sampah' }, { id: 'padaringan', name: 'Iuran Padaringan' },
      { id: 'setor', name: 'Setor Sampah RT' }, { id: 'pengeluaran', name: 'Pengeluaran' },
      { id: 'sumbangan', name: 'Sumbangan & Donasi' }, { id: 'bukukas', name: 'Buku Kas' },
      { id: 'aduan', name: 'Aduan Warga' }, { id: 'mpwa', name: 'MPWA Broadcast' },
      { id: 'laporan', name: 'Laporan & Analisis' }, { id: 'surat', name: 'Surat Menyurat' },
      { id: 'umkm', name: 'UMKM Warga' }, { id: 'kegiatan', name: 'Kegiatan RW' },
      { id: 'users', name: 'Manajemen Akun' }, { id: 'auditlog', name: 'Log Sistem' }
    ];

    App.showModal(`🛡️ Edit Role: ${role.label}`, `
      <div class="form-row form-row-2">
        <div class="form-group">
          <label class="form-label">Nama Role</label>
          <input type="text" class="form-input" id="fRoleName" value="${role.label}">
        </div>
        <div class="form-group">
          <label class="form-label">Warna Tema (Hex Code)</label>
          <div style="display:flex;gap:8px">
            <input type="color" id="fRoleColorPicker" value="${role.color}" style="width:40px;height:40px;padding:0;border:none;cursor:pointer" oninput="document.getElementById('fRoleColor').value=this.value">
            <input type="text" class="form-input" id="fRoleColor" value="${role.color}" style="flex:1" oninput="document.getElementById('fRoleColorPicker').value=this.value">
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Ikon Emoji</label>
        <input type="text" class="form-input" id="fRoleIcon" value="${role.icon}" maxlength="4">
      </div>

      <h4 style="font-size:13px;font-weight:700;margin:16px 0 8px;border-bottom:1px solid var(--border);padding-bottom:8px">Izin Hak Akses</h4>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
          <input type="checkbox" id="fRoleEdit" ${role.canEdit ? 'checked' : ''} style="width:16px;height:16px"> Bisa Tambah/Edit Data Umum
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
          <input type="checkbox" id="fRoleDelete" ${role.canDelete ? 'checked' : ''} style="width:16px;height:16px"> Bisa Hapus Data Permanen
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
          <input type="checkbox" id="fRoleSettings" ${role.canSettings ? 'checked' : ''} style="width:16px;height:16px"> Bisa Akses Pengaturan Global
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
          <input type="checkbox" id="fRoleManageUsers" ${role.canManageUsers ? 'checked' : ''} style="width:16px;height:16px"> Bisa Kelola Akun Pengguna
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;color:var(--merah)">
          <input type="checkbox" id="fRoleRTFilter" ${role.rtFilter ? 'checked' : ''} style="width:16px;height:16px"> Terkunci Filter RT Sendiri (Petugas RT)
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
          <input type="checkbox" id="fRoleReadOnly" ${role.readOnly ? 'checked' : ''} style="width:16px;height:16px"> Mode Baca Saja (Warga Biasa)
        </label>
      </div>

      <h4 style="font-size:13px;font-weight:700;margin:16px 0 8px;border-bottom:1px solid var(--border);padding-bottom:8px">Menu Terotorisasi</h4>
      <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:8px;margin-bottom:24px;font-size:12px;background:var(--bg2);padding:12px;border-radius:8px">
        ${allMenus.map(m => `
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
            <input type="checkbox" class="modal-menu-cb" value="${m.id}" ${(role.menus || []).includes(m.id) ? 'checked' : ''}>
            ${m.name}
          </label>
        `).join('')}
      </div>

      <button class="btn btn-primary w-full" onclick="Pages._saveRole('${roleId}')">💾 Simpan Perubahan Role</button>
    `);
  };

  Pages._saveRole = function (roleId) {
    if (!confirm('Perubahan pada Role akan langsung mengubah hak akses semua pengguna dengan Role ini. Lanjutkan?')) return;

    const label = document.getElementById('fRoleName').value.trim();
    const color = document.getElementById('fRoleColor').value.trim();
    const icon = document.getElementById('fRoleIcon').value.trim();

    const canEdit = document.getElementById('fRoleEdit').checked;
    const canDelete = document.getElementById('fRoleDelete').checked;
    const canSettings = document.getElementById('fRoleSettings').checked;
    const canManageUsers = document.getElementById('fRoleManageUsers').checked;
    const rtFilter = document.getElementById('fRoleRTFilter').checked;
    const readOnly = document.getElementById('fRoleReadOnly').checked;

    const menus = Array.from(document.querySelectorAll('.modal-menu-cb:checked')).map(cb => cb.value);

    if (!label || !color) return App.toast('Nama dan warna harus diisi', 'error');

    DB.update('roles', roleId, {
      label, color, icon,
      canEdit, canDelete, canSettings, canManageUsers, rtFilter, readOnly,
      menus
    });

    App.toast('✅ Pengaturan Role Berhasil Disimpan', 'success');
    App.closeModal();
    App.render('users');
    Auth.guardMenus(); // Re-apply UI guards for active session
  };


  Pages._saveUser = function (id) {
    const username = document.getElementById('fUserUsername')?.value?.trim()?.toLowerCase();
    const namaLengkap = document.getElementById('fUserNama')?.value?.trim();
    const level = document.getElementById('fUserLevel')?.value;
    const rt = document.getElementById('fUserRT')?.value || '';
    const aktif = document.getElementById('fUserAktif')?.value !== '0';

    if (!username || !namaLengkap || !level) return App.toast('Isi semua field yang wajib', 'error');

    // Validate username (no spaces, alphanumeric)
    if (!/^[a-z0-9_]+$/.test(username)) return App.toast('Username hanya boleh huruf kecil, angka, dan underscore', 'error');

    if (!id) {
      const pin = document.getElementById('fUserPIN')?.value;
      const pinConfirm = document.getElementById('fUserPINConfirm')?.value;
      if (!pin || pin.length !== 6) return App.toast('PIN harus 6 digit angka', 'error');
      if (!/^\d{6}$/.test(pin)) return App.toast('PIN hanya boleh angka', 'error');
      if (pin !== pinConfirm) return App.toast('Konfirmasi PIN tidak cocok', 'error');

      // Cek duplikat username
      const existing = DB.getAll('users').find(u => u.username === username);
      if (existing) return App.toast('Username sudah digunakan', 'error');

      DB.insert('users', { username, namaLengkap, pin, level, rt, aktif, createdAt: new Date().toISOString(), lastLogin: null });
      App.toast('✅ Akun berhasil dibuat', 'success');
    } else {
      DB.update('users', id, { namaLengkap, level, rt, aktif });
      App.toast('✅ Akun diperbarui', 'success');
    }
    App.closeModal();
    App.render('users');
  };

  Pages._resetPIN = function (id, username) {
    App.showModal(`🔑 Reset PIN — ${username}`, `
    <div style="text-align:center;padding:8px 0 16px">
      <div style="font-size:2rem;margin-bottom:8px">🔑</div>
      <p style="margin-bottom:16px;font-size:14px">Masukkan PIN baru untuk <strong>${username}</strong></p>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">PIN Baru (6 Digit)</label>
        <input type="password" class="form-input" id="fResetPIN" maxlength="6" inputmode="numeric" placeholder="6 digit angka">
      </div>
      <div class="form-group">
        <label class="form-label">Konfirmasi PIN Baru</label>
        <input type="password" class="form-input" id="fResetPINConf" maxlength="6" inputmode="numeric" placeholder="Ulangi PIN">
      </div>
    </div>
    <button class="btn btn-warning btn-block mt-3" onclick="Pages._doResetPIN('${id}')">🔑 Ganti PIN</button>
  `);
  };

  Pages._doResetPIN = function (id) {
    const pin = document.getElementById('fResetPIN')?.value;
    const conf = document.getElementById('fResetPINConf')?.value;
    if (!pin || pin.length !== 6) return App.toast('PIN harus 6 digit angka', 'error');
    if (!/^\d{6}$/.test(pin)) return App.toast('PIN hanya boleh angka', 'error');
    if (pin !== conf) return App.toast('Konfirmasi PIN tidak cocok', 'error');
    DB.update('users', id, { pin });
    App.closeModal();
    App.toast('✅ PIN berhasil diperbarui', 'success');
  };

  Pages._toggleAktif = function (id, currentAktif) {
    const aksi = currentAktif ? 'menonaktifkan' : 'mengaktifkan';
    if (!confirm(`Anda yakin ingin ${aksi} akun ini?`)) return;
    DB.update('users', id, { aktif: !currentAktif });
    App.render('users');
    App.toast(currentAktif ? '⏸️ Akun dinonaktifkan' : '▶️ Akun diaktifkan', 'info');
  };

  // === Global Audit Log ===
  Pages.auditlog = function () {
    // Guard: hanya superadmin dan ketua_rw
    if (!Auth.can('canSettings')) {
      return `<div class="empty-state"><div class="empty-state-icon">🔒</div><div class="empty-state-title">Akses Ditolak</div><div class="empty-state-subtitle">Anda tidak memiliki izin untuk melihat log sistem</div></div>`;
    }

    const logs = DB.getAll('auditLog').sort((a, b) => new Date(b.tanggal || b.timestamp) - new Date(a.tanggal || a.timestamp));

    return `
  <div class="toolbar">
    <div class="toolbar-left">
      <span style="font-size:13px;color:var(--text2)">📋 ${logs.length} aktivitas tersimpan</span>
    </div>
    <div class="toolbar-right">
      <button class="btn btn-danger" onclick="if(confirm('Hapus semua log secara permanen?')){DB._saveAll('auditLog',[]);App.render('auditlog');App.toast('Log dibersihkan','success')}">🗑️ Bersihkan Log</button>
    </div>
  </div>

  <div class="card">
    <div class="data-table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Operator</th>
            <th>Aksi</th>
            <th>Modul</th>
            <th>Deskripsi</th>
          </tr>
        </thead>
        <tbody>
          ${logs.length ? logs.slice(0, 500).map(log => {
      const ts = log.tanggal || log.timestamp || new Date().toISOString();
      const t = new Date(ts);
      const timeStr = `${t.getDate().toString().padStart(2, '0')}/${(t.getMonth() + 1).toString().padStart(2, '0')}/${t.getFullYear().toString().slice(-2)} ${t.getHours().toString().padStart(2, '0')}:${t.getMinutes().toString().padStart(2, '0')}`;

      let color = 'var(--text-tertiary)';
      let icon = '📝';
      const action = (log.aksi || log.action || '').toLowerCase();
      if (action.includes('tambah')) { color = 'var(--hijau)'; icon = '➕'; }
      else if (action.includes('edit') || action.includes('ubah')) { color = 'var(--kuning)'; icon = '✏️'; }
      else if (action.includes('hapus')) { color = 'var(--merah)'; icon = '🗑️'; }

      return `<tr>
              <td style="font-size:0.8rem;white-space:nowrap;color:var(--text2)">${timeStr}</td>
              <td style="font-weight:600;color:var(--text)">${log.operator || 'Sistem'}</td>
              <td><span class="badge" style="background:${color}11;color:${color};border:1px solid ${color}33">${icon} ${log.aksi || log.action || '-'}</span></td>
              <td><span style="font-size:0.85rem;color:var(--text2)">${log.collection || '-'}</span></td>
              <td style="font-size:0.85rem">${log.deskripsi || log.desc || '-'}</td>
            </tr>`;
    }).join('')
        : '<tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-title">Log Kosong</div></div></td></tr>'}
        </tbody>
      </table>
    </div>
    ${logs.length > 500 ? '<div style="text-align:center;padding:12px;font-size:0.85rem;color:var(--text-tertiary)">Hanya menampilkan 500 log terakhir</div>' : ''}
  </div>`;
  };

})();
