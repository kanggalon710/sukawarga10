/* Pages — Buku Kas (Transaction Ledger) */

Pages.bukukas = function () {
  const tahun = DB.getSetting('tahun', 2026);
  const months = ['', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
  const monthNames = ['Semua Bulan', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

  // Auto-render after page load
  setTimeout(() => Pages._renderBukuKas(), 50);

  return `
  <div class="toolbar" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;margin-bottom:14px">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <select class="filter-select" id="bkFilterKas" onchange="Pages._renderBukuKas()">
        <option value="">Semua Kas</option><option value="sampah">Kas Sampah</option><option value="padaringan">Kas Padaringan</option><option value="umum">Kas Umum</option>
      </select>
      <select class="filter-select" id="bkFilterBulan" onchange="Pages._renderBukuKas()">
        ${monthNames.map((m, i) => `<option value="${months[i]}">${m}</option>`).join('')}
      </select>
      <select class="filter-select" id="bkFilterTahun" onchange="Pages._renderBukuKas()">
        <option value="${tahun}">${tahun}</option>
        <option value="${tahun - 1}">${tahun - 1}</option>
      </select>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      ${Auth.can('canEdit') ? '<button class="btn btn-primary btn-sm" onclick="Pages._showFormTransaksi()">➕ Input Transaksi</button>' : ''}
      <button class="btn btn-outline btn-sm" onclick="Pages._exportBukuKas()">📥 Export CSV</button>
    </div>
  </div>
  <div id="bukukasContent"></div>`;
};

Pages._renderBukuKas = function () {
  const kas = document.getElementById('bkFilterKas')?.value || '';
  const bulan = document.getElementById('bkFilterBulan')?.value || '';
  const tahun = parseInt(document.getElementById('bkFilterTahun')?.value) || DB.getSetting('tahun', 2026);

  const filter = { tahun };
  if (kas) filter.kas = kas;
  if (bulan) filter.bulan = bulan;

  const trx = DB.getTransaksi(filter);

  // Running balance
  let balance = 0;
  const rows = trx.map((t, i) => {
    const isIn = t.jenis === 'masuk';
    balance += isIn ? t.jumlah : -t.jumlah;
    const kasLabel = { sampah: '🗑️ Sampah', padaringan: '💰 Padaringan', umum: '📦 Umum' }[t.kas] || t.kas;
    const kasBadge = t.kas === 'sampah' ? 'badge-gold' : t.kas === 'padaringan' ? 'badge-blue' : 'badge-gray';
    return `<tr>
      <td>${i + 1}</td>
      <td style="white-space:nowrap">${t.tanggal || '-'}</td>
      <td><span class="badge ${kasBadge}">${kasLabel}</span></td>
      <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${t.keterangan || ''}">${t.keterangan || '-'}</td>
      <td class="td-mono" style="color:var(--hijau);font-weight:600">${isIn ? App.formatRp(t.jumlah) : ''}</td>
      <td class="td-mono" style="color:var(--merah);font-weight:600">${!isIn ? App.formatRp(t.jumlah) : ''}</td>
      <td class="td-mono" style="color:${balance >= 0 ? 'var(--hijau)' : 'var(--merah)'};font-weight:700">${App.formatRp(balance)}</td>
    </tr>`;
  });

  // Totals
  const totalMasuk = trx.filter(t => t.jenis === 'masuk').reduce((s, t) => s + t.jumlah, 0);
  const totalKeluar = trx.filter(t => t.jenis !== 'masuk').reduce((s, t) => s + t.jumlah, 0);

  const container = document.getElementById('bukukasContent');
  if (!container) return;

  container.innerHTML = `
    <div class="iuran-summary-row" style="margin-bottom:14px">
      <div class="iuran-summary-card green">
        <div class="iuran-summary-icon">📥</div>
        <div>
          <div class="iuran-summary-value">${App.formatRp(totalMasuk)}</div>
          <div class="iuran-summary-label">TOTAL MASUK</div>
        </div>
      </div>
      <div class="iuran-summary-card red">
        <div class="iuran-summary-icon">📤</div>
        <div>
          <div class="iuran-summary-value">${App.formatRp(totalKeluar)}</div>
          <div class="iuran-summary-label">TOTAL KELUAR</div>
        </div>
      </div>
      <div class="iuran-summary-card blue">
        <div class="iuran-summary-icon">💰</div>
        <div>
          <div class="iuran-summary-value">${App.formatRp(balance)}</div>
          <div class="iuran-summary-label">SALDO AKHIR</div>
        </div>
      </div>
    </div>
    <div class="card"><div class="data-table-wrapper"><table class="data-table" id="tblBukuKas">
      <thead><tr><th>No</th><th>Tanggal</th><th>Kas</th><th>Keterangan</th><th>Debit (Masuk)</th><th>Kredit (Keluar)</th><th>Saldo</th></tr></thead>
      <tbody>${rows.length ? rows.join('') : '<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">📖</div><div class="empty-state-title">Belum ada transaksi</div><div class="empty-state-sub">Klik "Input Transaksi" untuk menambahkan</div></div></td></tr>'}</tbody>
      ${rows.length ? `<tfoot><tr style="border-top:2px solid var(--abu2)"><td colspan="4" style="font-weight:700">TOTAL (${trx.length} transaksi)</td><td class="td-mono" style="font-weight:700;color:var(--hijau)">${App.formatRp(totalMasuk)}</td><td class="td-mono" style="font-weight:700;color:var(--merah)">${App.formatRp(totalKeluar)}</td><td class="td-mono" style="font-weight:800;color:${balance >= 0 ? 'var(--hijau)' : 'var(--merah)'}">${App.formatRp(balance)}</td></tr></tfoot>` : ''}
    </table></div></div>
    <div style="text-align:center;margin-top:8px;font-size:11px;color:var(--text3)">${trx.length} transaksi tercatat · Tahun ${tahun}</div>`;
};

// === Form Input Transaksi Manual ===
Pages._showFormTransaksi = function () {
  const today = new Date().toISOString().slice(0, 10);
  App.showModal('➕ Input Transaksi Manual', `
    <div class="form-row-2">
      <div class="form-group">
        <label class="form-label">Jenis Transaksi *</label>
        <select class="form-select" id="fTrxJenis">
          <option value="masuk">📥 Pemasukan</option>
          <option value="keluar">📤 Pengeluaran</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Kas *</label>
        <select class="form-select" id="fTrxKas">
          <option value="sampah">🗑️ Kas Sampah</option>
          <option value="padaringan">💰 Kas Padaringan</option>
          <option value="umum">📦 Kas Umum</option>
        </select>
      </div>
    </div>
    <div class="form-row-2">
      <div class="form-group">
        <label class="form-label">Jumlah (Rp) *</label>
        <input class="form-input" id="fTrxJumlah" type="number" min="1000" placeholder="Contoh: 50000" inputmode="numeric">
      </div>
      <div class="form-group">
        <label class="form-label">Tanggal *</label>
        <input class="form-input" id="fTrxTanggal" type="date" value="${today}">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Kategori</label>
      <select class="form-select" id="fTrxKategori">
        <option value="iuran">Iuran</option>
        <option value="operasional">Operasional</option>
        <option value="sumbangan">Sumbangan/Donasi</option>
        <option value="belanja">Belanja</option>
        <option value="lainnya">Lainnya</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Keterangan *</label>
      <input class="form-input" id="fTrxKeterangan" placeholder="Contoh: Beli kantong sampah, sumbangan warga, dll">
    </div>
    <button class="btn btn-primary btn-block" onclick="Pages._saveTransaksi()">💾 Simpan Transaksi</button>
  `);
};

Pages._saveTransaksi = function () {
  const jenis = document.getElementById('fTrxJenis')?.value;
  const kas = document.getElementById('fTrxKas')?.value;
  const jumlah = parseInt(document.getElementById('fTrxJumlah')?.value) || 0;
  const tanggal = document.getElementById('fTrxTanggal')?.value;
  const kategori = document.getElementById('fTrxKategori')?.value || 'lainnya';
  const keterangan = document.getElementById('fTrxKeterangan')?.value?.trim();

  if (!jenis || !kas) return App.toast('Pilih jenis dan kas transaksi', 'error');
  if (jumlah < 1000) return App.toast('Jumlah minimal Rp 1.000', 'error');
  if (!tanggal) return App.toast('Tanggal wajib diisi', 'error');
  if (!keterangan) return App.toast('Keterangan wajib diisi', 'error');

  DB.addTransaksi({
    tanggal,
    jenis,
    kas,
    kategori,
    keterangan,
    jumlah,
  });

  App.closeModal();
  App.render('bukukas');
  App.toast('✅ Transaksi berhasil dicatat', 'success');
};

Pages._exportBukuKas = function () {
  const kas = document.getElementById('bkFilterKas')?.value || '';
  const bulan = document.getElementById('bkFilterBulan')?.value || '';
  const tahun = parseInt(document.getElementById('bkFilterTahun')?.value) || DB.getSetting('tahun', 2026);
  const filter = { tahun };
  if (kas) filter.kas = kas;
  if (bulan) filter.bulan = bulan;
  const trx = DB.getTransaksi(filter);

  let balance = 0;
  const rows = trx.map(t => {
    const isIn = t.jenis === 'masuk';
    balance += isIn ? t.jumlah : -t.jumlah;
    return { Tanggal: t.tanggal, Kas: t.kas, Keterangan: t.keterangan, Debit: isIn ? t.jumlah : '', Kredit: !isIn ? t.jumlah : '', Saldo: balance };
  });

  DB.exportCSV(rows, `buku_kas_${tahun}_${bulan || 'all'}.csv`);
  App.toast('CSV berhasil di-export!', 'success');
};
