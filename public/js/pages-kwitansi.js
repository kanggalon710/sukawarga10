/* Pages Kwitansi & Buku Kas */
(function() {
  window.Pages = window.Pages || {};
  const Pages = window.Pages;

// === Kwitansi Digital ===
Pages.kwitansi = function (data) {
  const bendahara = DB.getSetting('bendahara', 'Bendahara');
  const tgl = new Date(data.tanggal || new Date()).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });

  return `<div class="kwitansi-container" id="kwitansiPrint">
    <div class="kwitansi-header">
      <div class="kwitansi-title">KWITANSI IURAN</div>
      <div class="kwitansi-subtitle">RW 10 — Kel. Sukakarya, Kec. Tarogong Kidul</div>
    </div>
    <div class="kwitansi-divider"></div>
    <div class="kwitansi-row"><span class="kwitansi-label">No. Kwitansi</span><span class="kwitansi-value font-mono">${data.kwitansiNo || '-'}</span></div>
    <div class="kwitansi-row"><span class="kwitansi-label">Tanggal</span><span class="kwitansi-value">${tgl}</span></div>
    <div class="kwitansi-divider"></div>
    <div class="kwitansi-row"><span class="kwitansi-label">Nama KK</span><span class="kwitansi-value">${data.nama || '-'}</span></div>
    <div class="kwitansi-row"><span class="kwitansi-label">RT</span><span class="kwitansi-value">${data.rt || '-'}</span></div>
    <div class="kwitansi-divider"></div>
    <div class="kwitansi-row"><span class="kwitansi-label">Jenis Iuran</span><span class="kwitansi-value">${data.jenis || '-'}</span></div>
    <div class="kwitansi-row"><span class="kwitansi-label">Periode</span><span class="kwitansi-value">${data.periode || '-'}</span></div>
    <div class="kwitansi-amount-box">
      <span class="kwitansi-amount-label">Jumlah</span>
      <span class="kwitansi-amount-value">${App.formatRp(data.jumlah || 0)}</span>
    </div>
    <div class="kwitansi-row"><span class="kwitansi-label">Terbilang</span><span class="kwitansi-value" style="font-style:italic">${Pages._terbilang(data.jumlah || 0)} Rupiah</span></div>
    <div class="kwitansi-footer">
      <div class="kwitansi-sign">
        <div class="kwitansi-sign-label">Penerima</div>
        <div class="kwitansi-sign-line"></div>
        <div class="kwitansi-sign-name">(${data.nama || '...'})</div>
      </div>
      <div class="kwitansi-stamp">LUNAS</div>
      <div class="kwitansi-sign">
        <div class="kwitansi-sign-label">Bendahara RW</div>
        <div class="kwitansi-sign-line"></div>
        <div class="kwitansi-sign-name">(${bendahara})</div>
      </div>
    </div>
  </div>
  <div style="text-align:center;margin-top:16px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap" class="no-print">
    <button class="btn btn-primary" onclick="window.print()">🖨️ Cetak</button>
    <button class="btn btn-success" onclick="Pages._shareKwitansiWA('${(data.nama || '').replace(/'/g, "\\'")}','${data.kwitansiNo || ''}',${data.jumlah || 0},'${(data.jenis || '').replace(/'/g, "\\'")}','${data.periode || ''}','${(data.rt || '').replace(/'/g, "\\'")}')">📱 Kirim WA</button>
    <button class="btn" onclick="App.closeModal()">✕ Tutup</button>
  </div>`;
};

Pages._shareKwitansiWA = function (nama, no, jumlah, jenis, periode, rt) {
  const kk = DB.getAll('keluarga').find(k => k.nama === nama);
  const hp = (kk?.noHP || '').replace(/^0/, '62');
  const text = encodeURIComponent(`📄 *KWITANSI ${no}*\nNama: ${nama}\nRT: ${rt}\nJenis: ${jenis}\nPeriode: ${periode}\nJumlah: ${App.formatRp(jumlah)}\nStatus: ✅ LUNAS\n\n— Bendahara RW 10`);
  if (hp) window.open(`https://wa.me/${hp}?text=${text}`, '_blank');
  else { navigator.clipboard.writeText(decodeURIComponent(text)); App.toast('📋 Teks kwitansi disalin (no HP tidak ada)', 'info'); }
};

Pages._terbilang = function (n) {
  if (n === 0) return 'Nol';
  const angka = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
  if (n < 12) return angka[n];
  if (n < 20) return angka[n - 10] + ' Belas';
  if (n < 100) return angka[Math.floor(n / 10)] + ' Puluh' + (n % 10 ? ' ' + angka[n % 10] : '');
  if (n < 200) return 'Seratus' + (n % 100 ? ' ' + this._terbilang(n % 100) : '');
  if (n < 1000) return angka[Math.floor(n / 100)] + ' Ratus' + (n % 100 ? ' ' + this._terbilang(n % 100) : '');
  if (n < 2000) return 'Seribu' + (n % 1000 ? ' ' + this._terbilang(n % 1000) : '');
  if (n < 1000000) return this._terbilang(Math.floor(n / 1000)) + ' Ribu' + (n % 1000 ? ' ' + this._terbilang(n % 1000) : '');
  if (n < 1000000000) return this._terbilang(Math.floor(n / 1000000)) + ' Juta' + (n % 1000000 ? ' ' + this._terbilang(n % 1000000) : '');
  return this._terbilang(Math.floor(n / 1000000000)) + ' Milyar' + (n % 1000000000 ? ' ' + this._terbilang(n % 1000000000) : '');
};

// === Buku Kas ===
Pages.bukukas = function () {
  const tahun = DB.getSetting('tahun', 2026);
  const selKas = Pages._bkKas || '';
  const selBulan = Pages._bkBulan || '';
  const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];

  // Build filter
  const filter = { tahun };
  if (selKas) filter.kas = selKas;
  if (selBulan) filter.bulan = selBulan;

  // Get ALL transaksi for running balance (chronological, oldest first)
  let allTrx = DB.getTransaksi({ tahun });
  if (selKas) allTrx = allTrx.filter(t => t.kas === selKas);

  // Calculate running balance
  let running = 0;
  allTrx.forEach(t => {
    running += t.jenis === 'masuk' ? t.jumlah : -t.jumlah;
    t._saldo = running;
  });

  // Apply month filter AFTER running balance calc (so balance is correct)
  let displayed = allTrx;
  if (selBulan) displayed = allTrx.filter(t => t.tanggal && t.tanggal.slice(5, 7) === selBulan);

  // Reverse for display (newest first)
  displayed = [...displayed].reverse();

  // Summary
  const totalMasuk = displayed.filter(t => t.jenis === 'masuk').reduce((s, t) => s + t.jumlah, 0);
  const totalKeluar = displayed.filter(t => t.jenis === 'keluar').reduce((s, t) => s + t.jumlah, 0);
  const saldoAkhir = displayed.length ? displayed[0]._saldo : 0;
  const saldoAwal = displayed.length ? (displayed[displayed.length - 1]._saldo - (displayed[displayed.length - 1].jenis === 'masuk' ? displayed[displayed.length - 1].jumlah : -displayed[displayed.length - 1].jumlah)) : 0;

  return `
  <div class="toolbar">
    <div class="toolbar-left">
      <select class="filter-select" onchange="Pages._bkKas=this.value;App.render('bukukas')">
        <option value="" ${selKas === '' ? 'selected' : ''}>Semua Kas</option>
        <option value="sampah" ${selKas === 'sampah' ? 'selected' : ''}>Sampah</option>
        <option value="padaringan" ${selKas === 'padaringan' ? 'selected' : ''}>Padaringan</option>
      </select>
      <select class="filter-select" onchange="Pages._bkBulan=this.value;App.render('bukukas')">
        <option value="" ${selBulan === '' ? 'selected' : ''}>Semua Bulan</option>
        ${monthKeys.map((m, i) => `<option value="${String(i + 1).padStart(2, '0')}" ${selBulan === String(i + 1).padStart(2, '0') ? 'selected' : ''}>${m}</option>`).join('')}
      </select>
    </div>
    <div class="toolbar-right">
      <button class="btn" onclick="Pages._exportBukuKasCSV()">📥 Export CSV</button>
      <button class="btn btn-primary" onclick="Pages._showFormManualTrx()">➕ Transaksi</button>
    </div>
  </div>

  <div class="summary-bar">
    <div class="summary-item"><div class="summary-label">Saldo Awal</div><div class="summary-value blue">${App.formatRp(saldoAwal)}</div></div>
    <div class="summary-item"><div class="summary-label">Pemasukan</div><div class="summary-value green">${App.formatRp(totalMasuk)}</div></div>
    <div class="summary-item"><div class="summary-label">Pengeluaran</div><div class="summary-value red">${App.formatRp(totalKeluar)}</div></div>
    <div class="summary-item"><div class="summary-label">Saldo Akhir</div><div class="summary-value blue">${App.formatRp(saldoAkhir)}</div></div>
  </div>

  <div class="card"><div class="data-table-wrapper"><table class="data-table"><thead><tr><th>No</th><th>Tanggal</th><th>No. Ref</th><th>Keterangan</th><th>Debit</th><th>Kredit</th><th>Saldo</th></tr></thead><tbody>
  ${displayed.length ? displayed.map((t, i) => `<tr>
    <td>${i + 1}</td>
    <td style="white-space:nowrap">${t.tanggal || '-'}</td>
    <td class="font-mono" style="font-size:0.72rem">${t.refNo || t.id?.slice(0, 8) || '-'}</td>
    <td>${t.keterangan || '-'} <span class="badge ${t.kas === 'sampah' ? 'badge-info' : 'badge-warning'}" style="font-size:0.6rem">${t.kas}</span></td>
    <td class="text-success fw-bold">${t.jenis === 'masuk' ? App.formatRp(t.jumlah) : ''}</td>
    <td class="text-danger fw-bold">${t.jenis === 'keluar' ? App.formatRp(t.jumlah) : ''}</td>
    <td class="fw-bold">${App.formatRp(t._saldo)}</td>
  </tr>`).join('')
      : '<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">📖</div><div class="empty-state-title">Belum ada transaksi</div></div></td></tr>'}
  </tbody></table></div></div>`;
};

Pages._exportBukuKasCSV = function () {
  const tahun = DB.getSetting('tahun', 2026);
  let allTrx = DB.getTransaksi({ tahun });
  const selKas = Pages._bkKas || '';
  const selBulan = Pages._bkBulan || '';
  if (selKas) allTrx = allTrx.filter(t => t.kas === selKas);

  let running = 0;
  allTrx.forEach(t => { running += t.jenis === 'masuk' ? t.jumlah : -t.jumlah; t._saldo = running; });
  if (selBulan) allTrx = allTrx.filter(t => t.tanggal?.slice(5, 7) === selBulan);

  const rows = allTrx.map((t, i) => {
    const [y, m, d] = (t.tanggal || '').split('-');
    return {
      No: i + 1,
      Tanggal: d && m && y ? `${d}/${m}/${y}` : '',
      'No.Transaksi': t.refNo || '',
      Kas: t.kas || '',
      Kategori: t.kategori || '',
      Keterangan: t.keterangan || '',
      Debit: t.jenis === 'masuk' ? t.jumlah : 0,
      Kredit: t.jenis === 'keluar' ? t.jumlah : 0,
      Saldo: t._saldo
    };
  });
  DB.exportCSV(rows, `buku-kas-rw10-${tahun}.csv`);
  App.toast('📥 CSV diunduh', 'success');
};

Pages._showFormManualTrx = function () {
  App.showModal('Tambah Transaksi Manual', `
    <div class="form-row form-row-2">
      <div class="form-group"><label class="form-label">Jenis *</label><select class="form-select" id="fTrxJenis"><option value="masuk">Masuk (Debit)</option><option value="keluar">Keluar (Kredit)</option></select></div>
      <div class="form-group"><label class="form-label">Kas *</label><select class="form-select" id="fTrxKas"><option value="sampah">Sampah</option><option value="padaringan">Padaringan</option></select></div>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group"><label class="form-label">Tanggal</label><input type="date" class="form-input" id="fTrxDate" value="${new Date().toISOString().slice(0, 10)}"></div>
      <div class="form-group"><label class="form-label">Jumlah (Rp) *</label><input type="number" class="form-input" id="fTrxJumlah" inputmode="numeric" placeholder="0"></div>
    </div>
    <div class="form-group"><label class="form-label">Kategori</label><input type="text" class="form-input" id="fTrxKat" placeholder="Contoh: iuran, santunan, dll"></div>
    <div class="form-group"><label class="form-label">Keterangan *</label><input type="text" class="form-input" id="fTrxKet" placeholder="Keterangan transaksi"></div>
    <button class="btn btn-primary btn-block mt-3" onclick="Pages._saveManualTrx()">💾 Simpan</button>
  `);
};

Pages._saveManualTrx = function () {
  const jumlah = parseInt(document.getElementById('fTrxJumlah').value) || 0;
  const ket = document.getElementById('fTrxKet').value.trim();
  if (!jumlah || !ket) return App.toast('Lengkapi data', 'error');
  DB.addTransaksi({
    jenis: document.getElementById('fTrxJenis').value,
    kas: document.getElementById('fTrxKas').value,
    tanggal: document.getElementById('fTrxDate').value,
    kategori: document.getElementById('fTrxKat').value,
    keterangan: ket, jumlah
  });
  App.closeModal(); App.render('bukukas'); App.toast('Transaksi dicatat', 'success');
};

})();
