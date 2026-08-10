/* Pages Billing — Iuran, Setor, Pengeluaran, Sumbangan, Aduan, MPWA, Laporan */
(function () {
  window.Pages = window.Pages || {};
  const Pages = window.Pages;

  // === Iuran Sampah & Padaringan — Form Entry ===
  Pages._iuranSearch = '';
  Pages._iuranRT = '';
  Pages._iuranStatus = '';

  Pages.iuran = function (type) {
    const isSampah = type === 'sampah';
    const tahun = DB.getSetting('tahun', 2026);
    const tarifSampah = DB.getSetting('tarifSampah', 25000);
    const tarifPadaringan = DB.getSetting('tarifPadaringan', 15000);
    let keluarga = DB.getAll('keluarga').filter(k => (k.status || 'aktif') === 'aktif' && (isSampah ? k.ikutSampah !== false : k.ikutPadaringan !== false));
    const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
    const currentMonth = new Date().getMonth();
    const rts = [...new Set(keluarga.map(k => k.rt))].sort();

    // Filters
    const q = (Pages._iuranSearch || '').toLowerCase();
    const rtF = Pages._iuranRT || '';
    const statusF = Pages._iuranStatus || '';
    if (q) keluarga = keluarga.filter(k => k.nama.toLowerCase().includes(q));
    if (rtF) keluarga = keluarga.filter(k => k.rt === rtF);

    if (isSampah) {
      const selMonth = Pages._sampahMonth ?? currentMonth;
      const { keys: weeks, count: totalWeeks } = DB.getWeeksInMonth(selMonth, tahun);

      // Categorize
      const wargaData = keluarga.map(kk => {
        const record = DB.getIuranSampah(kk.id, tahun);
        let paidCount = 0;
        weeks.forEach(w => { if (DB._getSampahStatus(record, w) === 'lunas') paidCount++; });
        const allPaid = paidCount === totalWeeks;
        const lastDate = record?.weekDates ? Object.values(record.weekDates).sort().pop() : null;
        return { kk, record, paidCount, totalWeeks, allPaid, lastDate };
      });

      if (statusF === 'lunas') wargaData.splice(0, wargaData.length, ...wargaData.filter(w => w.allPaid));
      else if (statusF === 'belum') wargaData.splice(0, wargaData.length, ...wargaData.filter(w => !w.allPaid));

      const totalKK = wargaData.length;
      const lunasCount = wargaData.filter(w => w.allPaid).length;
      const belumCount = totalKK - lunasCount;

      const rows = wargaData.map((w, i) => {
        const statusCells = weeks.map(wk => {
          const st = DB._getSampahStatus(w.record, wk);
          const cls = st === 'lunas' ? 'iuran-dot lunas' : st === 'dispensasi' ? 'iuran-dot dispensasi' : 'iuran-dot belum';
          return `<span class="${cls}" title="${wk}: ${st}"></span>`;
        }).join('');

        return `<tr>
          <td>${i + 1}</td>
          <td class="clickable" onclick="Pages.showFormBayarSampah('${w.kk.id}')">${w.kk.nama}</td>
          <td><span class="badge badge-gray">${w.kk.rt}</span></td>
          <td><div class="iuran-dots">${statusCells}</div></td>
          <td><span class="badge ${w.allPaid ? 'badge-success' : w.paidCount > 0 ? 'badge-warning' : 'badge-danger'}">${w.paidCount}/${w.totalWeeks}</span></td>
          <td style="white-space:nowrap">${w.lastDate || '-'}</td>
          <td>${w.allPaid ? '<span class="text-success" style="font-size:12px">✅ Lunas</span>' : `<button class="btn btn-sm btn-primary" onclick="Pages.showFormBayarSampah('${w.kk.id}')">💰 Input Bayar</button>`}</td>
        </tr>`;
      }).join('');

      // Mobile cards
      const cards = wargaData.map(w => `<div class="billing-card">
        <div class="billing-card-header">
          <span class="billing-card-name clickable" onclick="Pages.showFormBayarSampah('${w.kk.id}')">${w.kk.nama}</span>
          <span class="badge ${w.allPaid ? 'badge-success' : w.paidCount > 0 ? 'badge-warning' : 'badge-danger'}">${w.paidCount}/${w.totalWeeks}</span>
        </div>
        <div class="billing-card-status">
          <span class="billing-card-rt">${w.kk.rt}</span>
          <span style="font-size:12px;color:var(--text3)">${w.lastDate ? 'Terakhir: ' + w.lastDate : ''}</span>
        </div>
        <div class="iuran-dots" style="margin:6px 0">${weeks.map(wk => {
        const st = DB._getSampahStatus(w.record, wk);
        return `<span class="iuran-dot ${st === 'lunas' ? 'lunas' : st === 'dispensasi' ? 'dispensasi' : 'belum'}" title="${wk}"></span>`;
      }).join('')}</div>
        ${w.allPaid ? `<button class="billing-card-btn paid-btn" disabled>✅ Lunas Semua</button>` :
          `<button class="billing-card-btn pay" onclick="Pages.showFormBayarSampah('${w.kk.id}')">💰 Input Pembayaran</button>`}
      </div>`).join('');

      return `
    <div class="animate-in">
      <div class="iuran-summary-row">
        <div class="iuran-summary-card green"><div class="iuran-summary-icon">✅</div><div class="iuran-summary-value">${lunasCount}</div><div class="iuran-summary-label">KK Lunas</div></div>
        <div class="iuran-summary-card red"><div class="iuran-summary-icon">⏳</div><div class="iuran-summary-value">${belumCount}</div><div class="iuran-summary-label">Belum Lunas</div></div>
        <div class="iuran-summary-card blue"><div class="iuran-summary-icon">💰</div><div class="iuran-summary-value">${App.formatRp(tarifSampah)}</div><div class="iuran-summary-label">Tarif / Minggu</div></div>
      </div>
      <div class="toolbar">
        <div class="toolbar-left">
          <select class="filter-select" onchange="Pages._sampahMonth=+this.value;App.render('sampah')">
            ${monthKeys.map((m, i) => `<option value="${i}" ${i === selMonth ? 'selected' : ''}>${m} ${tahun}</option>`).join('')}
          </select>
          <select class="filter-select" onchange="Pages._iuranRT=this.value;App.render('sampah')">
            <option value="">Semua RT</option>
            ${rts.map(r => `<option value="${r}" ${rtF === r ? 'selected' : ''}>${r}</option>`).join('')}
          </select>
          <select class="filter-select" onchange="Pages._iuranStatus=this.value;App.render('sampah')">
            <option value="">Semua Status</option>
            <option value="lunas" ${statusF === 'lunas' ? 'selected' : ''}>✅ Lunas</option>
            <option value="belum" ${statusF === 'belum' ? 'selected' : ''}>⏳ Belum</option>
          </select>
        </div>
        <div class="toolbar-right">
          <div class="toolbar-search">
            <input type="text" class="form-input" placeholder="🔍 Cari nama..." value="${Pages._iuranSearch || ''}" oninput="Pages._iuranSearch=this.value;App.render('sampah')">
          </div>
        </div>
      </div>
      <div class="card billing-grid-container">
        <table class="data-table"><thead><tr><th>No</th><th>Nama KK</th><th>RT</th><th>Status Minggu</th><th>Progress</th><th>Tgl Bayar</th><th>Aksi</th></tr></thead>
        <tbody>${rows || '<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-title">Tidak ada data</div></div></td></tr>'}</tbody></table>
      </div>
      <div class="billing-cards">${cards}</div>
    </div>`;
    } else {
      // Padaringan: list-based entry
      const wargaData = keluarga.map(kk => {
        const record = DB.getIuranPadaringan(kk.id, tahun);
        const paidMonths = monthKeys.filter(m => record?.months?.[m] > 0);
        const currentPaid = paidMonths.includes(monthKeys[currentMonth]);
        const totalPaid = paidMonths.reduce((s, m) => s + (record?.months?.[m] || 0), 0);
        const lastDate = record?.monthDates ? Object.values(record.monthDates).sort().pop() : null;
        return { kk, record, paidMonths, currentPaid, totalPaid, paidCount: paidMonths.length, lastDate };
      });

      if (statusF === 'lunas') wargaData.splice(0, wargaData.length, ...wargaData.filter(w => w.currentPaid));
      else if (statusF === 'belum') wargaData.splice(0, wargaData.length, ...wargaData.filter(w => !w.currentPaid));

      const totalKK = wargaData.length;
      const lunasCount = wargaData.filter(w => w.currentPaid).length;
      const belumCount = totalKK - lunasCount;

      const rows = wargaData.map((w, i) => {
        const monthDots = monthKeys.map(m => {
          const paid = w.record?.months?.[m] > 0;
          return `<span class="iuran-dot ${paid ? 'lunas' : 'belum'}" title="${m}: ${paid ? 'Lunas' : 'Belum'}"></span>`;
        }).join('');

        return `<tr>
          <td>${i + 1}</td>
          <td class="clickable" onclick="Pages.showFormBayarPadaringan('${w.kk.id}')">${w.kk.nama}</td>
          <td><span class="badge badge-gray">${w.kk.rt}</span></td>
          <td><div class="iuran-dots">${monthDots}</div></td>
          <td><span class="badge ${w.paidCount >= currentMonth + 1 ? 'badge-success' : w.paidCount > 0 ? 'badge-warning' : 'badge-danger'}">${w.paidCount}/12</span></td>
          <td class="td-mono">${App.formatRp(w.totalPaid)}</td>
          <td style="white-space:nowrap">${w.lastDate || '-'}</td>
          <td>${w.currentPaid ? '<span class="text-success" style="font-size:12px">✅ ' + monthKeys[currentMonth] + '</span>' : `<button class="btn btn-sm btn-primary" onclick="Pages.showFormBayarPadaringan('${w.kk.id}')">💰 Input Bayar</button>`}</td>
        </tr>`;
      }).join('');

      // Mobile cards
      const cards = wargaData.map(w => `<div class="billing-card">
        <div class="billing-card-header">
          <span class="billing-card-name clickable" onclick="Pages.showFormBayarPadaringan('${w.kk.id}')">${w.kk.nama}</span>
          <span class="badge ${w.currentPaid ? 'badge-success' : w.paidCount > 0 ? 'badge-warning' : 'badge-danger'}">${w.paidCount}/12</span>
        </div>
        <div class="billing-card-status">
          <span class="billing-card-rt">${w.kk.rt}</span>
          <span class="td-mono" style="font-size:12px">${App.formatRp(w.totalPaid)}</span>
        </div>
        <div class="iuran-dots" style="margin:6px 0">${monthKeys.map(m => {
        const paid = w.record?.months?.[m] > 0;
        return `<span class="iuran-dot ${paid ? 'lunas' : 'belum'}" title="${m}"></span>`;
      }).join('')}</div>
        ${w.currentPaid ? `<button class="billing-card-btn paid-btn" disabled>✅ ${monthKeys[currentMonth]} Lunas</button>` :
          `<button class="billing-card-btn pay" onclick="Pages.showFormBayarPadaringan('${w.kk.id}')">💰 Input Pembayaran</button>`}
      </div>`).join('');

      return `
    <div class="animate-in">
      <div class="iuran-summary-row">
        <div class="iuran-summary-card green"><div class="iuran-summary-icon">✅</div><div class="iuran-summary-value">${lunasCount}</div><div class="iuran-summary-label">Lunas ${monthKeys[currentMonth]}</div></div>
        <div class="iuran-summary-card red"><div class="iuran-summary-icon">⏳</div><div class="iuran-summary-value">${belumCount}</div><div class="iuran-summary-label">Belum Bayar</div></div>
        <div class="iuran-summary-card blue"><div class="iuran-summary-icon">💰</div><div class="iuran-summary-value">${App.formatRp(tarifPadaringan)}</div><div class="iuran-summary-label">Tarif / Bulan</div></div>
      </div>
      <div class="toolbar">
        <div class="toolbar-left">
          <select class="filter-select" onchange="Pages._iuranRT=this.value;App.render('padaringan')">
            <option value="">Semua RT</option>
            ${rts.map(r => `<option value="${r}" ${rtF === r ? 'selected' : ''}>${r}</option>`).join('')}
          </select>
          <select class="filter-select" onchange="Pages._iuranStatus=this.value;App.render('padaringan')">
            <option value="">Semua Status</option>
            <option value="lunas" ${statusF === 'lunas' ? 'selected' : ''}>✅ Lunas Bln Ini</option>
            <option value="belum" ${statusF === 'belum' ? 'selected' : ''}>⏳ Belum Bayar</option>
          </select>
          <span class="badge badge-info">${tahun}</span>
        </div>
        <div class="toolbar-right">
          <div class="toolbar-search">
            <input type="text" class="form-input" placeholder="🔍 Cari nama..." value="${Pages._iuranSearch || ''}" oninput="Pages._iuranSearch=this.value;App.render('padaringan')">
          </div>
        </div>
      </div>
      <div class="card billing-grid-container">
        <table class="data-table"><thead><tr><th>No</th><th>Nama KK</th><th>RT</th><th>Status Bulan</th><th>Progress</th><th>Total Bayar</th><th>Tgl Bayar</th><th>Aksi</th></tr></thead>
        <tbody>${rows || '<tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-title">Tidak ada data</div></div></td></tr>'}</tbody></table>
      </div>
      <div class="billing-cards">${cards}</div>
    </div>`;
    }
  };

  // === Form Bayar Sampah ===
  Pages.showFormBayarSampah = function (kkId) {
    const kk = DB.getById('keluarga', kkId);
    if (!kk) return App.toast('Data warga tidak ditemukan', 'error');
    const tahun = DB.getSetting('tahun', 2026);
    const selMonth = Pages._sampahMonth ?? new Date().getMonth();
    const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
    const { keys: weeks } = DB.getWeeksInMonth(selMonth, tahun);
    const record = DB.getIuranSampah(kkId, tahun);
    const tarif = DB.getSetting('tarifSampah', 25000);

    const weekCheckboxes = weeks.map(w => {
      const st = DB._getSampahStatus(record, w);
      const disabled = st === 'lunas' ? 'disabled checked' : '';
      const label = st === 'lunas' ? `${w} ✅ Lunas` : st === 'dispensasi' ? `${w} ⚡ Dispensasi` : w;
      return `<label class="iuran-week-label ${st}" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;background:${st === 'lunas' ? 'var(--hijau-pale)' : st === 'dispensasi' ? 'var(--emas-muda)' : 'var(--abu)'};margin-bottom:4px;cursor:${st === 'lunas' ? 'default' : 'pointer'}">
        <input type="checkbox" name="weekBayar" value="${w}" ${disabled} style="width:18px;height:18px;accent-color:var(--hijau)">
        <span style="flex:1;font-size:13px;font-weight:600">${label}</span>
        <span style="font-size:12px;color:var(--text3)">${App.formatRp(tarif)}</span>
      </label>`;
    }).join('');

    App.showModal('💰 Input Pembayaran Sampah', `
      <div style="background:var(--hijau-pale);padding:12px 16px;border-radius:10px;margin-bottom:16px;display:flex;align-items:center;gap:10px">
        <div style="width:40px;height:40px;border-radius:50%;background:var(--hijau);color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px">${(kk.nama || 'W').slice(0, 2).toUpperCase()}</div>
        <div>
          <div style="font-weight:700;font-size:14px">${kk.nama}</div>
          <div style="font-size:12px;color:var(--text3)">${kk.rt} · ${monthKeys[selMonth]} ${tahun}</div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Pilih Minggu yang Dibayar *</label>
        <div id="weekChecks">${weekCheckboxes}</div>
      </div>
      <div class="form-group">
        <label class="form-label">Tanggal Pembayaran</label>
        <input type="date" class="form-input" id="fSampahTgl" value="${new Date().toISOString().slice(0, 10)}">
      </div>
      <div style="background:var(--abu);padding:12px;border-radius:8px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;font-size:13px">
          <span>Tarif per Minggu</span><span class="td-mono">${App.formatRp(tarif)}</span>
        </div>
        <div id="fSampahTotal" style="display:flex;justify-content:space-between;font-size:14px;font-weight:700;margin-top:4px;color:var(--hijau)">
          <span>Total</span><span class="td-mono">${App.formatRp(0)}</span>
        </div>
      </div>
      <button class="btn btn-primary btn-block" onclick="Pages.saveBayarSampah('${kkId}')">💾 Simpan Pembayaran</button>
    `);

    // Live total calculation
    setTimeout(() => {
      document.querySelectorAll('input[name="weekBayar"]').forEach(cb => {
        cb.addEventListener('change', () => {
          const checked = document.querySelectorAll('input[name="weekBayar"]:checked:not(:disabled)').length;
          document.getElementById('fSampahTotal').innerHTML = `<span>Total</span><span class="td-mono">${App.formatRp(checked * tarif)}</span>`;
        });
      });
    }, 100);
  };

  Pages.saveBayarSampah = function (kkId) {
    const checked = [...document.querySelectorAll('input[name="weekBayar"]:checked:not(:disabled)')];
    if (!checked.length) return App.toast('Pilih minimal 1 minggu', 'error');
    const tgl = document.getElementById('fSampahTgl')?.value || new Date().toISOString().slice(0, 10);
    const tahun = DB.getSetting('tahun', 2026);
    const tarif = DB.getSetting('tarifSampah', 25000);
    const weekLabels = checked.map(cb => cb.value);
    checked.forEach(cb => {
      DB.setIuranSampahMinggu(kkId, tahun, cb.value, 'lunas', tgl);
    });
    App.closeModal();
    App.render('sampah');
    App.toast(`✅ ${checked.length} minggu berhasil dibayar`, 'success');

    // Auto-broadcast payment notification
    if (typeof MPWA !== 'undefined') {
      MPWA.sendPaymentNotif('sampah', kkId, {
        periode: weekLabels.join(', '),
        jumlah: checked.length * tarif,
        tanggal: tgl
      }).then(result => {
        if (result?.status === 'sent') App.toast(`📡 Notifikasi WA terkirim ke ${result.nama}`, 'success');
        else if (result?.status === 'failed') App.toast(`⚠️ Notif WA gagal: ${result.error}`, 'warning');
      }).catch(() => { });
    }
  };

  // === Form Bayar Padaringan ===
  Pages.showFormBayarPadaringan = function (kkId) {
    const kk = DB.getById('keluarga', kkId);
    if (!kk) return App.toast('Data warga tidak ditemukan', 'error');
    const tahun = DB.getSetting('tahun', 2026);
    const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
    const record = DB.getIuranPadaringan(kkId, tahun);
    const tarif = DB.getSetting('tarifPadaringan', 15000);
    const currentMonth = new Date().getMonth();

    // Find first unpaid month
    let defaultMonth = monthKeys[currentMonth];
    for (let i = 0; i < 12; i++) {
      if (!record?.months?.[monthKeys[i]] || record.months[monthKeys[i]] <= 0) {
        defaultMonth = monthKeys[i];
        break;
      }
    }

    const monthOptions = monthKeys.map(m => {
      const paid = record?.months?.[m] > 0;
      return `<option value="${m}" ${m === defaultMonth ? 'selected' : ''} ${paid ? 'disabled' : ''}>${m} ${paid ? '✅ Lunas' : ''}</option>`;
    }).join('');

    // Status grid
    const statusGrid = monthKeys.map(m => {
      const paid = record?.months?.[m] > 0;
      const date = record?.monthDates?.[m] || '';
      return `<div style="text-align:center;padding:4px">
        <div class="iuran-dot ${paid ? 'lunas' : 'belum'}" style="width:24px;height:24px;margin:0 auto;font-size:10px;line-height:24px">${m.slice(0, 1)}</div>
        <div style="font-size:9px;color:var(--text3);margin-top:2px">${m}</div>
      </div>`;
    }).join('');

    App.showModal('💰 Input Pembayaran Padaringan', `
      <div style="background:var(--hijau-pale);padding:12px 16px;border-radius:10px;margin-bottom:16px;display:flex;align-items:center;gap:10px">
        <div style="width:40px;height:40px;border-radius:50%;background:var(--hijau);color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px">${(kk.nama || 'W').slice(0, 2).toUpperCase()}</div>
        <div>
          <div style="font-weight:700;font-size:14px">${kk.nama}</div>
          <div style="font-size:12px;color:var(--text3)">${kk.rt} · Tahun ${tahun}</div>
        </div>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:2px;margin-bottom:16px;justify-content:center">${statusGrid}</div>
      <div class="form-row form-row-2">
        <div class="form-group">
          <label class="form-label">Bulan Pembayaran *</label>
          <select class="form-select" id="fPadBulan">${monthOptions}</select>
        </div>
        <div class="form-group">
          <label class="form-label">Nominal (Rp) *</label>
          <input type="number" class="form-input" id="fPadNominal" value="${tarif}" min="1000" step="1000">
          <small style="color:var(--text3);font-size:11px">Tarif standar: ${App.formatRp(tarif)}</small>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Tanggal Pembayaran</label>
        <input type="date" class="form-input" id="fPadTgl" value="${new Date().toISOString().slice(0, 10)}">
      </div>
      <button class="btn btn-primary btn-block" onclick="Pages.saveBayarPadaringan('${kkId}')">💾 Simpan Pembayaran</button>
    `);
  };

  Pages.saveBayarPadaringan = function (kkId) {
    const bulan = document.getElementById('fPadBulan')?.value;
    const nominal = parseInt(document.getElementById('fPadNominal')?.value) || 0;
    const tgl = document.getElementById('fPadTgl')?.value || new Date().toISOString().slice(0, 10);
    if (!bulan) return App.toast('Pilih bulan pembayaran', 'error');
    if (!nominal || nominal < 1000) return App.toast('Nominal minimal Rp 1.000', 'error');
    const tahun = DB.getSetting('tahun', 2026);
    DB.setIuranPadaringanBulan(kkId, tahun, bulan, true, nominal, tgl);
    App.closeModal();
    App.render('padaringan');
    App.toast(`✅ Iuran Padaringan ${bulan} berhasil dicatat`, 'success');

    // Auto-broadcast payment notification
    if (typeof MPWA !== 'undefined') {
      MPWA.sendPaymentNotif('padaringan', kkId, {
        periode: `${bulan} ${tahun}`,
        jumlah: nominal,
        tanggal: tgl
      }).then(result => {
        if (result?.status === 'sent') App.toast(`📡 Notifikasi WA terkirim ke ${result.nama}`, 'success');
        else if (result?.status === 'failed') App.toast(`⚠️ Notif WA gagal: ${result.error}`, 'warning');
      }).catch(() => { });
    }
  };

  // === Setor Sampah RT ===
  Pages.setor = function () {
    const data = DB.getAll('setorSampah');
    const total = data.reduce((s, d) => s + (d.jumlah || 0), 0);
    return `
  <div class="animate-in">
    <div class="toolbar">
      <div class="toolbar-left"><span class="badge badge-success">Total: ${App.formatRp(total)}</span></div>
      <div class="toolbar-right"><button class="btn btn-primary" onclick="Pages.showFormSetor()">➕ Tambah Setoran</button></div>
    </div>
    <div class="card"><div class="data-table-wrapper"><table class="data-table"><thead><tr><th>No</th><th>Tanggal</th><th>RT</th><th>Jumlah</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody>
    ${data.length ? data.map((d, i) => `<tr><td>${i + 1}</td><td>${d.tanggal || '-'}</td><td>${d.rt || '-'}</td><td class="text-success fw-bold">${App.formatRp(d.jumlah)}</td><td>${d.keterangan || '-'}</td><td><button class="btn btn-sm btn-danger" onclick="App.confirmDelete('setorSampah','${d.id}','Setoran ${d.rt}')">🗑️</button></td></tr>`).join('')
        : '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-title">Belum ada setoran</div></div></td></tr>'}
    </tbody></table></div></div>
  </div>`;
  };
  Pages.showFormSetor = function () {
    App.showModal('Tambah Setoran Sampah', `
    <div class="form-group"><label class="form-label">RT</label><select class="form-select" id="fSetorRT"><option>RT 01</option><option>RT 02</option><option>RT 03</option></select></div>
    <div class="form-group"><label class="form-label">Tanggal</label><input type="date" class="form-input" id="fSetorDate" value="${new Date().toISOString().slice(0, 10)}"></div>
    <div class="form-group"><label class="form-label">Jumlah (Rp)</label><input type="number" class="form-input" id="fSetorJumlah" placeholder="0"></div>
    <div class="form-group"><label class="form-label">Keterangan</label><input type="text" class="form-input" id="fSetorKet" placeholder="Opsional"></div>
    <button class="btn btn-primary btn-block mt-3" onclick="Pages.saveSetor()">💾 Simpan</button>
  `);
  };
  Pages.saveSetor = function () {
    const jumlah = parseInt(document.getElementById('fSetorJumlah').value) || 0;
    if (!jumlah) return App.toast('Jumlah harus diisi', 'error');
    const rt = document.getElementById('fSetorRT').value;
    DB.insert('setorSampah', { rt, tanggal: document.getElementById('fSetorDate').value, jumlah, keterangan: document.getElementById('fSetorKet').value });
    DB.addTransaksi({ jenis: 'masuk', kas: 'sampah', kategori: 'setoran', keterangan: `Setoran Sampah ${rt}`, jumlah });
    App.closeModal(); App.render('setor'); App.toast('Setoran disimpan', 'success');
  };

  // === Pengeluaran ===
  Pages.pengeluaran = function () {
    const data = DB.getAll('pengeluaran');
    const total = data.reduce((s, p) => s + (p.jumlah || 0), 0);
    return `
  <div class="animate-in">
    <div class="toolbar">
      <div class="toolbar-left">
        <select class="filter-select" onchange="Pages._filterPengeluaran(this.value)"><option value="">Semua</option><option value="sampah">Sampah</option><option value="padaringan">Padaringan</option></select>
        <span class="badge badge-warning">Total: ${App.formatRp(total)}</span>
      </div>
      <div class="toolbar-right"><button class="btn btn-primary" onclick="Pages.showFormPengeluaran()">➕ Tambah</button></div>
    </div>
    <div class="card"><div class="data-table-wrapper"><table class="data-table" id="tblPengeluaran"><thead><tr><th>No</th><th>Tanggal</th><th>Kas</th><th>Jenis</th><th>Keterangan</th><th>Jumlah</th><th>Penerima</th><th>Aksi</th></tr></thead><tbody>
    ${data.length ? data.map((p, i) => `<tr data-kat="${p.kategori}"><td>${i + 1}</td><td>${p.tanggal || '-'}</td><td><span class="badge ${p.kategori === 'sampah' ? 'badge-info' : 'badge-warning'}">${p.kategori}</span></td><td>${p.jenis || '-'}</td><td>${p.keterangan || '-'}</td><td class="text-danger fw-bold">${App.formatRp(p.jumlah)}</td><td>${p.penerima || '-'}</td><td><button class="btn btn-sm btn-danger" onclick="App.confirmDelete('pengeluaran','${p.id}','Pengeluaran ${p.keterangan}')">🗑️</button></td></tr>`).join('')
        : '<tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon">💸</div><div class="empty-state-title">Belum ada pengeluaran</div></div></td></tr>'}
    </tbody></table></div></div>
  </div>`;
  };
  Pages._filterPengeluaran = function (kat) {
    document.querySelectorAll('#tblPengeluaran tbody tr').forEach(tr => {
      tr.style.display = (!kat || tr.dataset.kat === kat) ? '' : 'none';
    });
  };
  Pages.showFormPengeluaran = function () {
    App.showModal('Tambah Pengeluaran', `
    <div class="form-row form-row-2">
      <div class="form-group"><label class="form-label">Kas *</label><select class="form-select" id="fPenKas"><option value="padaringan">Padaringan</option><option value="sampah">Sampah</option><option value="umum">Umum</option></select></div>
      <div class="form-group"><label class="form-label">Tanggal</label><input type="date" class="form-input" id="fPenDate" value="${new Date().toISOString().slice(0, 10)}"></div>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group"><label class="form-label">Kategori *</label><select class="form-select" id="fPenJenis"><option>Operasional</option><option>Sosial</option><option>Pembangunan</option><option>Kebersihan</option><option>Kegiatan</option><option>Santunan</option><option>Pembelian</option><option>Lain-lain</option></select></div>
      <div class="form-group"><label class="form-label">Jumlah (Rp) *</label><input type="number" class="form-input" id="fPenJumlah" placeholder="0"></div>
    </div>
    <div class="form-group"><label class="form-label">Keterangan *</label><input type="text" class="form-input" id="fPenKet" placeholder="Keterangan pengeluaran"></div>
    <div class="form-group"><label class="form-label">Penerima</label><input type="text" class="form-input" id="fPenPenerima"></div>
    <button class="btn btn-primary btn-block mt-3" onclick="Pages.savePengeluaran()">💾 Simpan</button>
  `);
  };
  Pages.savePengeluaran = function () {
    const jumlah = parseInt(document.getElementById('fPenJumlah').value) || 0;
    const ket = document.getElementById('fPenKet').value.trim();
    if (!jumlah || !ket) return App.toast('Lengkapi data', 'error');
    const kas = document.getElementById('fPenKas').value;
    const data = { tanggal: document.getElementById('fPenDate').value, kategori: kas, jenis: document.getElementById('fPenJenis').value, keterangan: ket, jumlah, penerima: document.getElementById('fPenPenerima').value, verifikasi: true };
    DB.insert('pengeluaran', data);
    DB.addTransaksi({ jenis: 'keluar', kas, kategori: data.jenis.toLowerCase(), keterangan: ket, jumlah });
    App.closeModal(); App.render('pengeluaran'); App.toast('Pengeluaran dicatat', 'success');
  };

  // === Sumbangan ===
  Pages.sumbangan = function () {
    const data = DB.getAll('sumbangan');
    const total = data.reduce((s, d) => s + (d.jumlah || 0), 0);
    return `
  <div class="animate-in">
    <div class="toolbar">
      <div class="toolbar-left"><span class="badge badge-success">Total: ${App.formatRp(total)}</span></div>
      <div class="toolbar-right"><button class="btn btn-primary" onclick="Pages.showFormSumbangan()">➕ Tambah</button></div>
    </div>
    <div class="card"><div class="data-table-wrapper"><table class="data-table"><thead><tr><th>No</th><th>Tanggal</th><th>Donatur</th><th>Jumlah</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody>
    ${data.length ? data.map((d, i) => `<tr><td>${i + 1}</td><td>${d.tanggal || '-'}</td><td>${d.donatur || '-'}</td><td class="text-success fw-bold">${App.formatRp(d.jumlah)}</td><td>${d.keterangan || '-'}</td><td><button class="btn btn-sm btn-danger" onclick="App.confirmDelete('sumbangan','${d.id}','Sumbangan dari ${d.donatur}')">🗑️</button></td></tr>`).join('')
        : '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">🎁</div><div class="empty-state-title">Belum ada sumbangan</div></div></td></tr>'}
    </tbody></table></div></div>
  </div>`;
  };
  Pages.showFormSumbangan = function () {
    App.showModal('Tambah Sumbangan', `
    <div class="form-group"><label class="form-label">Donatur *</label><input type="text" class="form-input" id="fSmbDonatur" placeholder="Nama donatur"></div>
    <div class="form-row form-row-2">
      <div class="form-group"><label class="form-label">Tanggal</label><input type="date" class="form-input" id="fSmbDate" value="${new Date().toISOString().slice(0, 10)}"></div>
      <div class="form-group"><label class="form-label">Jumlah (Rp) *</label><input type="number" class="form-input" id="fSmbJumlah" placeholder="0"></div>
    </div>
    <div class="form-group"><label class="form-label">Keterangan</label><input type="text" class="form-input" id="fSmbKet"></div>
    <button class="btn btn-primary btn-block mt-3" onclick="Pages.saveSumbangan()">💾 Simpan</button>
  `);
  };
  Pages.saveSumbangan = function () {
    const jumlah = parseInt(document.getElementById('fSmbJumlah').value) || 0;
    const donatur = document.getElementById('fSmbDonatur').value.trim();
    if (!jumlah || !donatur) return App.toast('Lengkapi data', 'error');
    DB.insert('sumbangan', { tanggal: document.getElementById('fSmbDate').value, donatur, jumlah, keterangan: document.getElementById('fSmbKet').value });
    DB.addTransaksi({ jenis: 'masuk', kas: 'padaringan', kategori: 'sumbangan', keterangan: `Sumbangan dari ${donatur}`, jumlah });
    App.closeModal(); App.render('sumbangan'); App.toast('Sumbangan dicatat', 'success');
  };

  // === Aduan Warga — with Kategori, Prioritas, Tracking ===
  Pages._aduanFilter = '';
  Pages.aduan = function () {
    let allData = DB.getAll('aduan').sort((a, b) => (b.createdAt || b.tanggal || '').localeCompare(a.createdAt || a.tanggal || ''));
    const session = Auth.getSession();
    const levelInfo = Auth.getLevelInfo(Auth.currentLevel());
    const isReadOnly = !!levelInfo.readOnly;
    const isPengurus = !isReadOnly; // RT, Bendahara, RW, Admin

    // === Role-based data filter ===
    let data = [...allData];
    if (isReadOnly && session) {
      data = data.filter(a => a.userId === session.id);
    } else if (levelInfo.rtFilter && session?.rt) {
      data = data.filter(a => a.rt === session.rt);
    }

    const filter = Pages._aduanFilter;
    if (filter) data = data.filter(a => a.status === filter);

    // Stats
    const total = data.length;
    const masuk = data.filter(a => a.status === 'masuk').length;
    const proses = data.filter(a => a.status === 'ditindaklanjuti').length;
    const selesai = data.filter(a => a.status === 'selesai').length;
    const ditolak = data.filter(a => a.status === 'ditolak').length;

    setTimeout(() => { const b = document.getElementById('navNotifAduan'); if (b) { b.textContent = masuk || ''; b.style.display = masuk ? 'flex' : 'none'; } }, 0);

    const statusBadge = (s) => { const m = { masuk: 'badge-info', ditindaklanjuti: 'badge-warning', selesai: 'badge-success', ditolak: 'badge-danger' }; return `<span class="badge ${m[s] || 'badge-info'}">${s || 'masuk'}</span>`; };
    const priBadge = (p) => { const m = { tinggi: 'badge-danger', sedang: 'badge-warning', rendah: 'badge-info' }; return `<span class="badge ${m[p] || 'badge-info'}">${p || 'sedang'}</span>`; };

    // === Stats Dashboard (for Pengurus) ===
    const statsHtml = isPengurus ? `
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px">
      <div class="stat-card" style="cursor:pointer;${filter === 'masuk' ? 'border:2px solid var(--biru)' : ''}" onclick="Pages._aduanFilter='masuk';App.render('aduan')">
        <div class="stat-icon-box" style="background:var(--biru)15;color:var(--biru)">📥</div>
        <div class="stat-label">Masuk</div><div class="stat-value">${masuk}</div>
      </div>
      <div class="stat-card" style="cursor:pointer;${filter === 'ditindaklanjuti' ? 'border:2px solid var(--emas)' : ''}" onclick="Pages._aduanFilter='ditindaklanjuti';App.render('aduan')">
        <div class="stat-icon-box" style="background:var(--emas)15;color:var(--emas)">🔄</div>
        <div class="stat-label">Proses</div><div class="stat-value">${proses}</div>
      </div>
      <div class="stat-card" style="cursor:pointer;${filter === 'selesai' ? 'border:2px solid var(--hijau)' : ''}" onclick="Pages._aduanFilter='selesai';App.render('aduan')">
        <div class="stat-icon-box" style="background:var(--hijau)15;color:var(--hijau)">✅</div>
        <div class="stat-label">Selesai</div><div class="stat-value">${selesai}</div>
      </div>
      <div class="stat-card" style="cursor:pointer;${filter === 'ditolak' ? 'border:2px solid var(--merah)' : ''}" onclick="Pages._aduanFilter='ditolak';App.render('aduan')">
        <div class="stat-icon-box" style="background:var(--merah)15;color:var(--merah)">❌</div>
        <div class="stat-label">Ditolak</div><div class="stat-value">${ditolak}</div>
      </div>
    </div>` : '';

    // === Table rows ===
    const tableRows = data.length ? data.map((a, i) => {
      const userInfo = a.userId ? DB.getById('users', a.userId) : null;
      const pelaporDisplay = userInfo ? `<strong>${userInfo.namaLengkap || a.pelapor}</strong>` : (a.pelapor || '-');
      return `<tr>
        <td>${i + 1}</td>
        <td style="white-space:nowrap">${a.tanggal || '-'}</td>
        <td>${pelaporDisplay} <span class="badge badge-gray" style="font-size:10px">${a.rt || ''}</span></td>
        <td>${a.kategori || '-'}</td>
        <td>${priBadge(a.prioritas)}</td>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${(a.isi || '').replace(/"/g, '&quot;')}">${a.isi || '-'}</td>
        <td>${isPengurus ? `<select class="filter-select" style="min-height:32px;font-size:0.75rem" onchange="DB.update('aduan','${a.id}',{status:this.value});App.render('aduan');App.toast('Status diperbarui')"><option value="masuk" ${a.status === 'masuk' ? 'selected' : ''}>Masuk</option><option value="ditindaklanjuti" ${a.status === 'ditindaklanjuti' ? 'selected' : ''}>Proses</option><option value="selesai" ${a.status === 'selesai' ? 'selected' : ''}>Selesai</option><option value="ditolak" ${a.status === 'ditolak' ? 'selected' : ''}>Ditolak</option></select>` : statusBadge(a.status)}</td>
        <td style="white-space:nowrap">
          <button class="btn btn-sm btn-outline" onclick="Pages._showAduanDetail('${a.id}')" title="Detail">📋</button>
          ${Auth.can('canDelete') ? ` <button class="btn btn-sm btn-danger" onclick="App.confirmDelete('aduan','${a.id}','Aduan #${i + 1}')" title="Hapus">🗑️</button>` : ''}
        </td>
      </tr>`;
    }).join('') : '<tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon">📝</div><div class="empty-state-title">Belum ada aduan</div></div></td></tr>';

    return `
  <div class="animate-in">
    <div class="toolbar">
      <div class="toolbar-left">
        ${isPengurus ? `
          <select class="filter-select" onchange="Pages._aduanFilter=this.value;App.render('aduan')">
            <option value="">Semua (${total})</option>
            <option value="masuk" ${filter === 'masuk' ? 'selected' : ''}>📥 Masuk (${masuk})</option>
            <option value="ditindaklanjuti" ${filter === 'ditindaklanjuti' ? 'selected' : ''}>🔄 Proses (${proses})</option>
            <option value="selesai" ${filter === 'selesai' ? 'selected' : ''}>✅ Selesai (${selesai})</option>
            <option value="ditolak" ${filter === 'ditolak' ? 'selected' : ''}>❌ Ditolak (${ditolak})</option>
          </select>
          ${masuk ? `<span class="badge badge-warning">${masuk} aduan baru</span>` : '<span class="badge badge-success">Semua ditangani ✓</span>'}
        ` : `<span style="font-size:13px;color:var(--text2)">📝 ${total} aduan Anda</span>`}
      </div>
      <div class="toolbar-right">
        <button class="btn btn-primary" onclick="Pages.showFormAduan()">${isPengurus ? '📋 Catat Aduan Warga' : '➕ Laporkan Aduan Baru'}</button>
      </div>
    </div>

    ${statsHtml}

    <div class="card"><div class="data-table-wrapper"><table class="data-table"><thead><tr><th>No</th><th>Tanggal</th><th>Pelapor</th><th>Kategori</th><th>Prioritas</th><th>Isi</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    ${tableRows}
    </tbody></table></div></div>
  </div>`;
  };

  Pages._showAduanDetail = function (id) {
    const a = DB.getById('aduan', id); if (!a) return;
    const userInfo = a.userId ? DB.getById('users', a.userId) : null;
    const levelInfo = Auth.getLevelInfo(Auth.currentLevel());
    const isPengurus = !levelInfo.readOnly;
    const pelaporInfo = userInfo
      ? `<strong>${userInfo.namaLengkap}</strong> <span class="badge badge-gray" style="font-size:10px">${Auth.getLevelInfo(userInfo.level)?.icon || ''} ${Auth.getLevelInfo(userInfo.level)?.label || userInfo.level}</span>`
      : (a.pelapor || '-');

    App.showModal('📋 Detail Aduan', `
      <div class="data-table-wrapper"><table class="data-table">
        <tr><td style="width:30%">Pelapor</td><td>${pelaporInfo}</td></tr>
        <tr><td>RT</td><td>${a.rt || '-'}</td></tr>
        <tr><td>Kategori</td><td>${a.kategori || '-'}</td></tr>
        <tr><td>Prioritas</td><td>${a.prioritas || 'sedang'}</td></tr>
        <tr><td>Tanggal</td><td>${a.tanggal || '-'}</td></tr>
        <tr><td>Status</td><td>${a.status || 'masuk'}</td></tr>
        <tr><td>Isi Aduan</td><td>${a.isi || '-'}</td></tr>
        <tr><td>Catatan Tindak Lanjut</td><td>${a.catatanTL || '<em style="color:var(--text3)">Belum ada</em>'}</td></tr>
      </table></div>
      ${isPengurus ? `
        <div class="form-group" style="margin-top:12px"><label class="form-label">Tambah Catatan Tindak Lanjut</label><textarea class="form-textarea" id="fAduanCTL">${a.catatanTL || ''}</textarea></div>
        <button class="btn btn-primary btn-block" onclick="DB.update('aduan','${id}',{catatanTL:document.getElementById('fAduanCTL').value});App.closeModal();App.render('aduan');App.toast('Catatan disimpan')">💾 Simpan Catatan</button>
      ` : ''}
    `);
  };

  Pages.showFormAduan = function () {
    const session = Auth.getSession();
    const levelInfo = Auth.getLevelInfo(Auth.currentLevel());
    const isReadOnly = !!levelInfo.readOnly;
    const rtList = [...new Set(DB.getAll('keluarga').map(k => k.rt))].sort();

    App.showModal(isReadOnly ? '➕ Laporkan Aduan' : '📋 Catat Aduan Warga', `
    ${isReadOnly ? `
      <div style="padding:8px 12px;background:var(--hijau)08;border:1px solid var(--hijau)22;border-radius:8px;margin-bottom:12px;font-size:12px;color:var(--text2)">
        🧑 Pelapor: <strong>${session?.namaLengkap || '-'}</strong> · ${session?.rt || 'RT -'}
      </div>
    ` : `
      <div class="form-row form-row-2">
        <div class="form-group"><label class="form-label">Nama Pelapor *</label><input type="text" class="form-input" id="fAduanPelapor" placeholder="Nama warga yang melapor"></div>
        <div class="form-group"><label class="form-label">RT</label><select class="form-select" id="fAduanRT">${rtList.map(r => `<option ${session?.rt === r ? 'selected' : ''}>${r}</option>`).join('')}</select></div>
      </div>
    `}
    <div class="form-row form-row-2">
      <div class="form-group"><label class="form-label">Kategori</label><select class="form-select" id="fAduanKategori"><option value="Infrastruktur">Infrastruktur</option><option value="Keamanan">Keamanan</option><option value="Kebersihan">Kebersihan</option><option value="Ketertiban">Ketertiban</option><option value="Sosial">Sosial</option><option value="Lainnya">Lainnya</option></select></div>
      <div class="form-group"><label class="form-label">Prioritas</label><select class="form-select" id="fAduanPrioritas"><option value="rendah">Rendah</option><option value="sedang" selected>Sedang</option><option value="tinggi">Tinggi</option></select></div>
    </div>
    <div class="form-group"><label class="form-label">Isi Aduan *</label><textarea class="form-textarea" id="fAduanIsi" placeholder="Jelaskan keluhan atau aduan secara lengkap..."></textarea></div>
    <button class="btn btn-primary btn-block mt-3" onclick="Pages.saveAduan()">💾 Simpan Aduan</button>
  `);
  };

  Pages.saveAduan = function () {
    const isi = document.getElementById('fAduanIsi')?.value?.trim();
    if (!isi) return App.toast('Isi aduan wajib diisi', 'error');

    const session = Auth.getSession();
    const levelInfo = Auth.getLevelInfo(Auth.currentLevel());
    const isReadOnly = !!levelInfo.readOnly;

    let pelapor, rt, userId;
    if (isReadOnly) {
      // Warga: auto from session
      pelapor = session?.namaLengkap || 'Anonim';
      rt = session?.rt || '';
      userId = session?.id || '';
    } else {
      // Pengurus: from form (catat on behalf)
      pelapor = document.getElementById('fAduanPelapor')?.value?.trim() || session?.namaLengkap || 'Anonim';
      rt = document.getElementById('fAduanRT')?.value || session?.rt || '';
      userId = ''; // on behalf, no userId linkage
    }

    DB.insert('aduan', {
      tanggal: new Date().toISOString().slice(0, 10),
      createdAt: new Date().toISOString(),
      pelapor,
      rt,
      userId,
      kategori: document.getElementById('fAduanKategori')?.value || 'Lainnya',
      prioritas: document.getElementById('fAduanPrioritas')?.value || 'sedang',
      isi,
      status: 'masuk',
      catatanTL: '',
      recordedBy: session?.namaLengkap || 'System'
    });
    App.closeModal(); App.render('aduan'); App.toast('✅ Aduan berhasil dicatat', 'success');
  };

  // === MPWA Broadcast — 3 Realistic Modes ===
  Pages.mpwa = function () {
    const tahun = DB.getSetting('tahun', 2026);
    const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
    const bulan = monthKeys[new Date().getMonth()];
    const keluarga = DB.getAll('keluarga').filter(k => (k.status || 'aktif') === 'aktif');

    // Collect tunggakan
    const tunggakan = [];
    keluarga.forEach(kk => {
      const items = [];
      const pad = DB.getIuranPadaringan(kk.id, tahun);
      if (!pad?.months?.[bulan] || pad.months[bulan] <= 0) items.push('Padaringan');
      const { start, end } = DB.getWeeksInMonth(new Date().getMonth());
      const samp = DB.getIuranSampah(kk.id, tahun);
      let unpaid = 0;
      for (let w = start; w <= end; w++) if (!samp?.weeks?.['M' + w] || samp.weeks['M' + w] <= 0) unpaid++;
      if (unpaid > 0) items.push(`Sampah (${unpaid} minggu)`);
      if (items.length) tunggakan.push({ kk, items, hp: kk.noHP });
    });

    const tab = Pages._mpwaTab || 'reminder';

    // Build broadcast text
    const broadcastText = `📢 *REMINDER IURAN ${bulan} ${tahun}*\n\nBerikut warga yang belum bayar:\n${tunggakan.map((t, i) => `${i + 1}. ${t.kk.nama} (${t.kk.rt}) — ${t.items.join(' + ')}`).join('\n')}\n\nMohon segera konfirmasi ke Ketua RT masing-masing.\n— Pengurus RW 10`;

    return `
  <div class="animate-in">
    <div class="tab-nav">
      <button class="tab-btn ${tab === 'reminder' ? 'active' : ''}" onclick="Pages._mpwaTab='reminder';App.render('mpwa')">📱 Reminder Tunggakan</button>
      <button class="tab-btn ${tab === 'template' ? 'active' : ''}" onclick="Pages._mpwaTab='template';App.render('mpwa')">📝 Template Pesan</button>
    </div>

    ${tab === 'reminder' ? `
    <div class="alert-cards-row">
      <div class="alert-card alert-warning"><div class="alert-card-icon">⚠️</div><div><strong>${tunggakan.length} warga belum lunas</strong><small>Bulan ${bulan} ${tahun}</small></div></div>
    </div>

    <div class="card mb-3">
      <h3 style="margin-bottom:12px;font-size:0.92rem">Mode 1 — Salin Semua Nomor HP</h3>
      <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:8px">Nomor HP warga yang belum lunas (format yang bisa di-paste ke grup WA):</p>
      <div class="font-mono" style="background:var(--bg-glass);padding:12px;border-radius:8px;word-break:break-all;font-size:0.82rem" id="allPhones">${tunggakan.filter(t => t.hp).map(t => t.hp).join(', ') || 'Tidak ada nomor HP'}</div>
      <button class="btn btn-primary mt-2" onclick="Pages._copyText(document.getElementById('allPhones').textContent)">📋 Salin Semua Nomor</button>
    </div>

    <div class="card mb-3">
      <h3 style="margin-bottom:12px;font-size:0.92rem">Mode 2 — Pesan Individual via WhatsApp</h3>
      <div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Nama</th><th>RT</th><th>Tunggakan</th><th>Aksi</th></tr></thead><tbody>
      ${tunggakan.map(t => {
      const hp = (t.hp || '').replace(/^0/, '62');
      const msg = encodeURIComponent(`Yth. Bpk/Ibu ${t.kk.nama},\n\nMohon segera melunasi iuran ${t.items.join(' dan ')} bulan ${bulan} ${tahun}.\n\nTerima kasih,\nPengurus RW 10`);
      return `<tr><td>${t.kk.nama}</td><td>${t.kk.rt}</td><td>${t.items.join(', ')}</td><td>${hp ? `<a class="btn btn-sm btn-success" href="https://wa.me/${hp}?text=${msg}" target="_blank">📱 WA</a>` : '<span class="text-muted">No HP?</span>'}</td></tr>`;
    }).join('')}
      </tbody></table></div>
    </div>

    <div class="card">
      <h3 style="margin-bottom:12px;font-size:0.92rem">Mode 3 — Broadcast ke Grup WA</h3>
      <div class="wa-preview"><div class="wa-bubble">${broadcastText}</div><div class="wa-time">${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}</div></div>
      <button class="btn btn-primary mt-3" onclick="Pages._copyText(\`${broadcastText.replace(/`/g, '\\`')}\`)">📋 Salin Pesan</button>
    </div>
    ` : `
      ${(() => {
        const tarifS = App.formatRp(DB.getSetting('tarifSampah', 5000));
        const tarifP = App.formatRp(DB.getSetting('tarifPadaringan', 5000));
        const namaRW = DB.getSetting('namaRW', 'RW 10');
        const kelurahan = DB.getSetting('kelurahan', 'Sukakarya');
        const bendahara = DB.getSetting('bendahara', 'Bendahara');
        return `
    <div class="card mb-3">
      <h3 style="margin-bottom:12px;font-size:0.92rem">1. Pengumuman Iuran Bulanan</h3>
      <textarea class="form-textarea" id="tplIuran" rows="6">📢 *PENGUMUMAN IURAN ${bulan} ${tahun}*\n\nAssalamualaikum Wr. Wb.\n\nKepada seluruh warga ${namaRW} Kel. ${kelurahan},\nMohon segera melunasi iuran bulan ${bulan}:\n• Iuran Sampah: ${tarifS}/minggu\n• Iuran Padaringan: ${tarifP}/bulan\n\nPembayaran melalui Ketua RT masing-masing.\n\nTerima kasih atas kerjasamanya.\n— Pengurus ${namaRW}</textarea>
      <button class="btn btn-sm btn-primary mt-2" onclick="Pages._copyText(document.getElementById('tplIuran').value)">📋 Salin</button>
    </div>
    <div class="card mb-3">
      <h3 style="margin-bottom:12px;font-size:0.92rem">2. Laporan Keuangan Ringkas</h3>
      <textarea class="form-textarea" id="tplLaporan" rows="6">📊 *LAPORAN KEUANGAN ${namaRW}*\nPeriode: ${bulan} ${tahun}\n\n💰 Kas Sampah: ${App.formatRp(DB.getSaldo('sampah', tahun).saldo)}\n💰 Kas Padaringan: ${App.formatRp(DB.getSaldo('padaringan', tahun).saldo)}\n💰 Kas Umum: ${App.formatRp(DB.getSaldo('umum', tahun).saldo)}\n\nTotal Saldo: ${App.formatRp(DB.getSaldo('sampah', tahun).saldo + DB.getSaldo('padaringan', tahun).saldo + DB.getSaldo('umum', tahun).saldo)}\n\nLaporan lengkap tersedia di sistem SukaWarga10.\n— ${bendahara}, Bendahara ${namaRW}</textarea>
      <button class="btn btn-sm btn-primary mt-2" onclick="Pages._copyText(document.getElementById('tplLaporan').value)">📋 Salin</button>
    </div>
    <div class="card">
      <h3 style="margin-bottom:12px;font-size:0.92rem">3. Pengumuman Kegiatan RW</h3>
      <textarea class="form-textarea" id="tplKegiatan" rows="6">📣 *PENGUMUMAN KEGIATAN ${namaRW}*\n\nAssalamualaikum Wr. Wb.\n\nDiberitahukan akan diadakan:\n📌 Kegiatan: [Nama Kegiatan]\n📅 Tanggal: [Tanggal]\n⏰ Waktu: [Waktu]\n📍 Tempat: [Lokasi]\n\nMohon kehadiran seluruh warga.\n— Pengurus ${namaRW}</textarea>
      <button class="btn btn-sm btn-primary mt-2" onclick="Pages._copyText(document.getElementById('tplKegiatan').value)">📋 Salin</button>
    </div>
    `;
      })()}
    `}
  </div>
  `;
  };

  Pages._copyText = function (text) {
    navigator.clipboard.writeText(text).then(() => App.toast('📋 Teks disalin!', 'success')).catch(() => {
      const ta = document.createElement('textarea');
      ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
      App.toast('📋 Teks disalin!', 'success');
    });
  };

  // === Laporan & Analisis — 5 Tabs ===
  Pages.laporan = function () {
    const tab = Pages._laporanTab || 'bulanan';
    const tahun = DB.getSetting('tahun', 2026);
    const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
    const namaRW = DB.getSetting('namaRW', 'RW 10');
    const kelurahan = DB.getSetting('kelurahan', 'Sukakarya');

    return `<div class="animate-in">
  <div class="tab-nav">
    <button class="tab-btn ${tab === 'bulanan' ? 'active' : ''}" onclick="Pages._laporanTab='bulanan';App.render('laporan')">📅 Bulanan</button>
    <button class="tab-btn ${tab === 'kepatuhan' ? 'active' : ''}" onclick="Pages._laporanTab='kepatuhan';App.render('laporan')">📊 Kepatuhan</button>
    <button class="tab-btn ${tab === 'ranking' ? 'active' : ''}" onclick="Pages._laporanTab='ranking';App.render('laporan')">🏆 Ranking RT</button>
    <button class="tab-btn ${tab === 'tahunan' ? 'active' : ''}" onclick="Pages._laporanTab='tahunan';App.render('laporan')">📈 Tahunan</button>
    <button class="tab-btn ${tab === 'sosial' ? 'active' : ''}" onclick="Pages._laporanTab='sosial';App.render('laporan')">🤝 Sosial</button>
  </div>
  ${Pages['_laporan_' + tab](tahun, monthKeys, namaRW, kelurahan)}
  </div>`;
  };

  Pages._laporan_bulanan = function (tahun, monthKeys, namaRW, kelurahan) {
    const selBulan = Pages._laporanBulan ?? new Date().getMonth();
    const bulan = monthKeys[selBulan];
    const bulanFull = new Date(tahun, selBulan).toLocaleDateString('id-ID', { month: 'long' });
    const rek = DB.getRekonsiliasi(bulan, tahun);
    const keluarga = DB.getAll('keluarga').filter(k => (k.status || 'aktif') === 'aktif');
    const { keys: weekKeys, count: totalWeeks } = DB.getWeeksInMonth(selBulan, tahun);

    // Categorize warga
    const lunas = [], cicilan = [], belum = [];
    keluarga.forEach(kk => {
      const pad = DB.getIuranPadaringan(kk.id, tahun);
      const padPaid = pad?.months?.[bulan] > 0;
      const samp = DB.getIuranSampah(kk.id, tahun);
      let sampPaid = 0;
      weekKeys.forEach(wk => { if (samp?.weeks?.[wk] > 0) sampPaid++; });
      const sampFull = sampPaid === totalWeeks;
      if (padPaid && sampFull) lunas.push({ kk, sampPaid, sampTotal: totalWeeks });
      else if (padPaid || sampPaid > 0) cicilan.push({ kk, padPaid, sampPaid, sampTotal: totalWeeks });
      else belum.push(kk);
    });

    // Pengeluaran bulan ini from transaksi
    const bulanPad = String(selBulan + 1).padStart(2, '0');
    const pengeluaranBulan = DB.getTransaksi({ tahun }).filter(t => t.jenis === 'keluar' && t.tanggal?.slice(5, 7) === bulanPad);
    const totalPengeluaran = pengeluaranBulan.reduce((s, t) => s + (t.jumlah || 0), 0);
    const totalPemasukan = rek.sampah.terbayar + rek.padaringan.terbayar;
    const saldoBulan = totalPemasukan - totalPengeluaran;
    const kepatuhanAvg = keluarga.length ? Math.round((lunas.length + cicilan.length * 0.5) / keluarga.length * 100) : 0;

    return `
  <div class="toolbar">
    <div class="toolbar-left">
      <select class="filter-select" onchange="Pages._laporanBulan=+this.value;App.render('laporan')">
        ${monthKeys.map((m, i) => `<option value="${i}" ${i === selBulan ? 'selected' : ''}>${m} ${tahun}</option>`).join('')}
      </select>
    </div>
    <div class="toolbar-right"><button class="btn btn-primary" onclick="window.print()">🖨️ Cetak Laporan</button></div>
  </div>

  <div class="print-header hidden" id="printHeader">
    <h2>LAPORAN KEUANGAN BULANAN</h2>
    <p>${namaRW} Kel. ${kelurahan} — Bulan ${bulanFull} ${tahun}</p>
  </div>

  <div class="summary-bar">
    <div class="summary-item">
      <div class="summary-label">💰 Pemasukan Sampah</div>
      <div class="summary-value green">${App.formatRp(rek.sampah.terbayar)}</div>
    </div>
    <div class="summary-item">
      <div class="summary-label">💰 Pemasukan Padaringan</div>
      <div class="summary-value green">${App.formatRp(rek.padaringan.terbayar)}</div>
    </div>
    <div class="summary-item">
      <div class="summary-label">💸 Total Pengeluaran</div>
      <div class="summary-value red">${App.formatRp(totalPengeluaran)}</div>
    </div>
    <div class="summary-item">
      <div class="summary-label">📊 Saldo Bulan</div>
      <div class="summary-value ${saldoBulan >= 0 ? 'blue' : 'red'}">${App.formatRp(saldoBulan)}</div>
    </div>
  </div>

  <div class="status-cards-row">
    <div class="status-summary-card lunas">
      <div class="status-icon">✅</div>
      <div class="status-info">
        <div class="status-count">${lunas.length}</div>
        <div class="status-label">KK Lunas</div>
      </div>
    </div>
    <div class="status-summary-card cicilan">
      <div class="status-icon">⏳</div>
      <div class="status-info">
        <div class="status-count">${cicilan.length}</div>
        <div class="status-label">KK Cicilan</div>
      </div>
    </div>
    <div class="status-summary-card belum">
      <div class="status-icon">❌</div>
      <div class="status-info">
        <div class="status-count">${belum.length}</div>
        <div class="status-label">KK Belum Bayar</div>
      </div>
    </div>
  </div>

  <div class="card print-section mb-3">
    <div class="card-header" style="margin-bottom:8px">
      <div class="card-title">📋 Rekonsiliasi Keuangan</div>
      <span class="badge ${kepatuhanAvg >= 75 ? 'badge-green' : kepatuhanAvg >= 50 ? 'badge-gold' : 'badge-red'}">Kepatuhan: ${kepatuhanAvg}%</span>
    </div>
    <div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Kas</th><th>Tagihan</th><th>Terbayar</th><th>Outstanding</th><th>%</th></tr></thead><tbody>
    <tr><td><strong>🗑️ Sampah</strong></td><td>${App.formatRp(rek.sampah.tagihan)}</td><td class="text-success">${App.formatRp(rek.sampah.terbayar)}</td><td class="text-danger">${App.formatRp(rek.sampah.outstanding)}</td><td><span class="badge ${rek.sampah.kepatuhan >= 75 ? 'badge-green' : 'badge-red'}">${rek.sampah.kepatuhan}%</span></td></tr>
    <tr><td><strong>💰 Padaringan</strong></td><td>${App.formatRp(rek.padaringan.tagihan)}</td><td class="text-success">${App.formatRp(rek.padaringan.terbayar)}</td><td class="text-danger">${App.formatRp(rek.padaringan.outstanding)}</td><td><span class="badge ${rek.padaringan.kepatuhan >= 75 ? 'badge-green' : 'badge-red'}">${rek.padaringan.kepatuhan}%</span></td></tr>
    <tr style="font-weight:700;background:var(--abu2)"><td>Total</td><td>${App.formatRp(rek.sampah.tagihan + rek.padaringan.tagihan)}</td><td class="text-success">${App.formatRp(totalPemasukan)}</td><td class="text-danger">${App.formatRp(rek.sampah.outstanding + rek.padaringan.outstanding)}</td><td></td></tr>
    </tbody></table></div>
  </div>

  ${lunas.length ? `<div class="card print-section mb-3">
    <div class="card-header" style="margin-bottom:8px">
      <div class="card-title">✅ Lunas Semua Iuran</div>
      <span class="badge badge-green">${lunas.length} KK</span>
    </div>
    <div class="data-table-wrapper"><table class="data-table"><thead><tr><th>No</th><th>Nama KK</th><th>RT</th><th>Sampah</th></tr></thead><tbody>
    ${lunas.map((l, i) => `<tr><td>${i + 1}</td><td class="clickable" onclick="App.showDetailKK('${l.kk.id}')">${l.kk.nama}</td><td>${l.kk.rt}</td><td><span class="badge badge-green">${l.sampPaid}/${l.sampTotal} ✓</span></td></tr>`).join('')}
    </tbody></table></div>
  </div>` : ''}

  ${cicilan.length ? `<div class="card print-section mb-3">
    <div class="card-header" style="margin-bottom:8px">
      <div class="card-title">⏳ Pembayaran Sebagian</div>
      <span class="badge badge-gold">${cicilan.length} KK</span>
    </div>
    <div class="data-table-wrapper"><table class="data-table"><thead><tr><th>No</th><th>Nama KK</th><th>RT</th><th>Sampah</th><th>Padaringan</th></tr></thead><tbody>
    ${cicilan.map((c, i) => `<tr><td>${i + 1}</td><td class="clickable" onclick="App.showDetailKK('${c.kk.id}')">${c.kk.nama}</td><td>${c.kk.rt}</td><td>${c.sampPaid}/${c.sampTotal}</td><td>${c.padPaid ? '<span class="badge badge-green">✅</span>' : '<span class="badge badge-red">❌</span>'}</td></tr>`).join('')}
    </tbody></table></div>
  </div>` : ''}

  ${belum.length ? `<div class="card print-section mb-3">
    <div class="card-header" style="margin-bottom:8px">
      <div class="card-title">❌ Belum Bayar Sama Sekali</div>
      <span class="badge badge-red">${belum.length} KK</span>
    </div>
    <div class="data-table-wrapper"><table class="data-table"><thead><tr><th>No</th><th>Nama KK</th><th>RT</th><th>No. HP</th></tr></thead><tbody>
    ${belum.map((b, i) => `<tr><td>${i + 1}</td><td class="clickable" onclick="App.showDetailKK('${b.id}')">${b.nama}</td><td>${b.rt}</td><td>${b.noHP || '-'}</td></tr>`).join('')}
    </tbody></table></div>
  </div>` : '<div class="card mb-3"><div class="card-title" style="text-align:center;padding:20px 0">🎉 Semua warga sudah membayar!</div></div>'}

  ${pengeluaranBulan.length ? `<div class="card print-section">
    <div class="card-header" style="margin-bottom:8px">
      <div class="card-title">💸 Detail Pengeluaran</div>
      <span class="badge badge-red">${App.formatRp(totalPengeluaran)}</span>
    </div>
    <div class="data-table-wrapper"><table class="data-table"><thead><tr><th>No</th><th>Tanggal</th><th>Kas</th><th>Kategori</th><th>Keterangan</th><th>Jumlah</th></tr></thead><tbody>
    ${pengeluaranBulan.map((t, i) => `<tr><td>${i + 1}</td><td>${t.tanggal || '-'}</td><td><span class="badge badge-blue">${t.kas || '-'}</span></td><td>${t.kategori || '-'}</td><td>${t.keterangan || '-'}</td><td class="text-danger td-mono">${App.formatRp(t.jumlah)}</td></tr>`).join('')}
    </tbody></table></div>
  </div>` : ''}`;
  };

  Pages._laporan_kepatuhan = function (tahun) {
    const keluarga = DB.getAll('keluarga').filter(k => (k.status || 'aktif') === 'aktif');
    const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
    const currentMonthIdx = new Date().getMonth();
    const monthsElapsed = currentMonthIdx + 1;

    const rows = keluarga.map(kk => {
      const pad = DB.getIuranPadaringan(kk.id, tahun);
      const paidMonths = monthKeys.filter(m => pad?.months?.[m] > 0).length;
      const pct = Math.round(paidMonths / monthsElapsed * 100);
      return { kk, paidMonths, pct };
    }).sort((a, b) => b.pct - a.pct);

    const excellent = rows.filter(r => r.pct >= 90).length;
    const good = rows.filter(r => r.pct >= 50 && r.pct < 90).length;
    const poor = rows.filter(r => r.pct < 50).length;

    return `
  <div class="report-grid">
    <div class="report-kpi"><div class="report-kpi-icon">🌟</div><div class="report-kpi-value" style="color:var(--hijau)">${excellent}</div><div class="report-kpi-label">Excellent (≥90%)</div></div>
    <div class="report-kpi"><div class="report-kpi-icon">👍</div><div class="report-kpi-value" style="color:var(--emas)">${good}</div><div class="report-kpi-label">Good (50-89%)</div></div>
    <div class="report-kpi"><div class="report-kpi-icon">⚠️</div><div class="report-kpi-value" style="color:var(--merah)">${poor}</div><div class="report-kpi-label">Perlu Perhatian (<50%)</div></div>
  </div>

  <div class="card"><div class="card-header" style="margin-bottom:8px">
    <div class="card-title">📊 Kepatuhan Iuran Padaringan ${tahun}</div>
    <span class="badge badge-info">${monthsElapsed} bulan berjalan</span>
  </div>
  <div class="data-table-wrapper"><table class="data-table"><thead><tr><th>No</th><th>Nama KK</th><th>RT</th><th>Bulan Bayar</th><th>Kepatuhan</th><th>Progress</th></tr></thead><tbody>
  ${rows.map((r, i) => `<tr>
    <td>${i + 1}</td>
    <td class="clickable" onclick="App.showDetailKK('${r.kk.id}')">${r.kk.nama}</td>
    <td>${r.kk.rt}</td>
    <td>${r.paidMonths}/${monthsElapsed}</td>
    <td><span class="badge ${r.pct >= 90 ? 'badge-green' : r.pct >= 50 ? 'badge-gold' : 'badge-red'}">${r.pct}%</span></td>
    <td style="min-width:100px"><div class="progress-bar"><div class="progress-fill ${r.pct >= 90 ? 'green' : r.pct >= 50 ? 'gold' : 'red'}" style="width:${r.pct}%"></div></div></td>
  </tr>`).join('')}
  </tbody></table></div></div>`;
  };

  Pages._laporan_ranking = function (tahun) {
    const stats = DB.getStats();
    const perRT = (stats.perRT || []).sort((a, b) => (b.sampah + b.padaringan) - (a.sampah + a.padaringan));
    const medals = ['🥇', '🥈', '🥉'];

    return `
  <div class="card"><div class="card-header" style="margin-bottom:8px">
    <div class="card-title">🏆 Ranking Pemasukan per RT — ${tahun}</div>
  </div>
  <div class="data-table-wrapper"><table class="data-table"><thead><tr><th>#</th><th>RT</th><th>Total KK</th><th>Pemasukan Sampah</th><th>Pemasukan Padaringan</th><th>Total</th></tr></thead><tbody>
  ${perRT.map((rt, i) => {
      const total = rt.sampah + rt.padaringan;
      return `<tr${i === 0 ? ' style="background:var(--hijau-pale)"' : ''}>
      <td style="font-size:18px">${medals[i] || (i + 1)}</td>
      <td><strong>${rt.rt}</strong></td>
      <td>${rt.totalKK}</td>
      <td class="text-success td-mono">${App.formatRp(rt.sampah)}</td>
      <td class="text-success td-mono">${App.formatRp(rt.padaringan)}</td>
      <td class="td-mono" style="font-weight:800">${App.formatRp(total)}</td>
    </tr>`;
    }).join('')}
  </tbody></table></div></div>`;
  };

  Pages._laporan_tahunan = function (tahun, monthKeys) {
    const maxH = 140;
    let rows = [];
    let maxVal = 0;
    let totalMasuk = 0, totalKeluar = 0;

    monthKeys.forEach((m, i) => {
      const bulanPad = String(i + 1).padStart(2, '0');
      const trx = DB.getTransaksi({ tahun }).filter(t => t.tanggal?.slice(5, 7) === bulanPad);
      const masuk = trx.filter(t => t.jenis === 'masuk').reduce((s, t) => s + (t.jumlah || 0), 0);
      const keluar = trx.filter(t => t.jenis === 'keluar').reduce((s, t) => s + (t.jumlah || 0), 0);
      if (masuk > maxVal) maxVal = masuk;
      if (keluar > maxVal) maxVal = keluar;
      totalMasuk += masuk;
      totalKeluar += keluar;
      rows.push({ m, masuk, keluar });
    });

    return `
  <div class="report-grid">
    <div class="report-kpi"><div class="report-kpi-icon">📥</div><div class="report-kpi-value" style="color:var(--hijau)">${App.formatRp(totalMasuk)}</div><div class="report-kpi-label">Total Pemasukan</div></div>
    <div class="report-kpi"><div class="report-kpi-icon">📤</div><div class="report-kpi-value" style="color:var(--merah)">${App.formatRp(totalKeluar)}</div><div class="report-kpi-label">Total Pengeluaran</div></div>
    <div class="report-kpi"><div class="report-kpi-icon">💎</div><div class="report-kpi-value" style="color:var(--biru)">${App.formatRp(totalMasuk - totalKeluar)}</div><div class="report-kpi-label">Surplus / Defisit</div></div>
  </div>

  <div class="card mb-3">
    <div class="card-header" style="margin-bottom:4px">
      <div class="card-title">📈 Tren Pemasukan vs Pengeluaran ${tahun}</div>
    </div>
    <div class="chart-bar-container">
    ${rows.map(r => `<div class="chart-bar-item">
      <div class="chart-bar emerald" style="height:${maxVal ? Math.max(2, r.masuk / maxVal * maxH) : 2}px" title="Masuk: ${App.formatRp(r.masuk)}"></div>
      <div class="chart-bar rose" style="height:${maxVal ? Math.max(2, r.keluar / maxVal * maxH) : 2}px;margin-top:2px" title="Keluar: ${App.formatRp(r.keluar)}"></div>
      <div class="chart-bar-label">${r.m}</div>
    </div>`).join('')}
    </div>
    <div class="chart-legend">
      <span><span class="chart-legend-dot" style="background:var(--hijau)"></span>Pemasukan</span>
      <span><span class="chart-legend-dot" style="background:var(--merah)"></span>Pengeluaran</span>
    </div>
  </div>

  <div class="card">
    <div class="card-title" style="margin-bottom:8px">📋 Rincian per Bulan</div>
    <div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Bulan</th><th>Pemasukan</th><th>Pengeluaran</th><th>Saldo</th></tr></thead><tbody>
    ${rows.map(r => {
      const saldo = r.masuk - r.keluar;
      return `<tr><td><strong>${r.m}</strong></td><td class="text-success td-mono">${App.formatRp(r.masuk)}</td><td class="text-danger td-mono">${App.formatRp(r.keluar)}</td><td class="td-mono" style="font-weight:700;color:${saldo >= 0 ? 'var(--hijau)' : 'var(--merah)'}">${App.formatRp(saldo)}</td></tr>`;
    }).join('')}
    <tr style="font-weight:800;background:var(--abu2)"><td>TOTAL</td><td class="text-success td-mono">${App.formatRp(totalMasuk)}</td><td class="text-danger td-mono">${App.formatRp(totalKeluar)}</td><td class="td-mono" style="color:${totalMasuk - totalKeluar >= 0 ? 'var(--hijau)' : 'var(--merah)'}">${App.formatRp(totalMasuk - totalKeluar)}</td></tr>
    </tbody></table></div>
  </div>`;
  };

  Pages._laporan_sosial = function () {
    const keluarga = DB.getAll('keluarga');
    const totalKK = keluarga.length;
    const rentan = keluarga.filter(k => { const t = k.tags || []; return t.includes('lansia') || t.includes('difabel') || t.includes('janda') || t.includes('prioritas') || t.includes('bansos'); });

    const byPenghasilan = {};
    keluarga.forEach(k => { const p = k.penghasilan || 'Tidak diketahui'; byPenghasilan[p] = (byPenghasilan[p] || 0) + 1; });
    const penghasilanRows = Object.entries(byPenghasilan).sort((a, b) => b[1] - a[1]);

    const tagCounts = {};
    keluarga.forEach(k => (k.tags || []).forEach(t => { tagCounts[t] = (tagCounts[t] || 0) + 1; }));
    const tagRows = Object.entries(tagCounts).sort((a, b) => b[1] - a[1]);

    return `
  <div class="report-grid">
    <div class="report-kpi"><div class="report-kpi-icon">👨‍👩‍👧‍👦</div><div class="report-kpi-value">${totalKK}</div><div class="report-kpi-label">Total KK</div></div>
    <div class="report-kpi"><div class="report-kpi-icon">🤝</div><div class="report-kpi-value" style="color:var(--emas)">${rentan.length}</div><div class="report-kpi-label">Warga Rentan</div></div>
    <div class="report-kpi"><div class="report-kpi-icon">🏷️</div><div class="report-kpi-value" style="color:var(--biru)">${tagRows.length}</div><div class="report-kpi-label">Jenis Tag</div></div>
  </div>

  <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
    <div class="card">
      <div class="card-title" style="margin-bottom:8px">💰 Distribusi Penghasilan</div>
      <div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Penghasilan</th><th>Jumlah</th><th>%</th><th>Progress</th></tr></thead><tbody>
      ${penghasilanRows.map(([p, c]) => {
      const pct = Math.round(c / totalKK * 100);
      return `<tr><td>${p}</td><td><strong>${c}</strong></td><td>${pct}%</td><td style="min-width:80px"><div class="progress-bar"><div class="progress-fill blue" style="width:${pct}%"></div></div></td></tr>`;
    }).join('')}
      </tbody></table></div>
    </div>

    <div class="card">
      <div class="card-title" style="margin-bottom:8px">🏷️ Statistik Tag</div>
      <div class="data-table-wrapper"><table class="data-table"><thead><tr><th>Tag</th><th>Jumlah KK</th><th>%</th></tr></thead><tbody>
      ${tagRows.length ? tagRows.map(([t, c]) => `<tr><td><span class="tag-chip">${t}</span></td><td><strong>${c}</strong></td><td>${Math.round(c / totalKK * 100)}%</td></tr>`).join('')
        : '<tr><td colspan="3" style="text-align:center;color:var(--text3)">Belum ada tag</td></tr>'}
      </tbody></table></div>
    </div>
  </div>

  ${rentan.length ? `<div class="card">
    <div class="card-header" style="margin-bottom:8px">
      <div class="card-title">🤝 Warga Rentan & Penerima Bansos</div>
      <span class="badge badge-gold">${rentan.length} KK</span>
    </div>
    <div class="data-table-wrapper"><table class="data-table"><thead><tr><th>No</th><th>Nama KK</th><th>RT</th><th>Tag</th><th>Penghasilan</th></tr></thead><tbody>
    ${rentan.map((k, i) => `<tr><td>${i + 1}</td><td class="clickable" onclick="App.showDetailKK('${k.id}')">${k.nama}</td><td>${k.rt}</td><td>${(k.tags || []).map(t => `<span class="tag-chip">${t}</span>`).join(' ')}</td><td>${k.penghasilan || '-'}</td></tr>`).join('')}
    </tbody></table></div>
  </div>` : '<div class="card"><div style="text-align:center;padding:20px;color:var(--text3)">Tidak ada data warga rentan</div></div>'}
  `;
  };

})();
