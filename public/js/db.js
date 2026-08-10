/* ========================================
   DATABASE LAYER — LocalStorage CRUD
   Billing RW 10 Kel. Sukakarya
   v3.0 — Single Source of Truth: Transaksi
   ======================================== */

const DB = {
  PREFIX: 'billing_rw10_',

  // === Core CRUD ===
  _getAll(c) { try { return JSON.parse(localStorage.getItem(this.PREFIX + c)) || []; } catch { return []; } },
  _saveAll(c, d) {
    localStorage.setItem(this.PREFIX + c, JSON.stringify(d));
    if (!this._isSyncing && ['keluarga', 'anggota', 'iuranSampah', 'iuranPadaringan', 'transaksi', 'pengeluaran', 'setorSampah', 'sumbangan'].includes(c)) {
      this._isSyncing = true;
      setTimeout(() => { this.syncUp(); this._isSyncing = false; }, 2000);
    }
  },
  generateId() { return Date.now().toString(36) + Math.random().toString(36).substr(2, 6); },
  getAll(c) { return this._getAll(c); },
  getById(c, id) { return this._getAll(c).find(i => i.id === id) || null; },
  count(c) { return this._getAll(c).length; },
  query(c, fn) { return this._getAll(c).filter(fn); },

  insert(c, data, skipLog) {
    const items = this._getAll(c);
    const rec = { id: this.generateId(), ...data, createdAt: new Date().toISOString(), updatedAt: new Date().toISOString() };
    items.push(rec);
    this._saveAll(c, items);
    if (!skipLog && c !== 'auditLog' && c !== 'transaksi') {
      const nama = data.nama || data.keterangan || data.donatur || rec.id;
      this.logActivity('TAMBAH', c, rec.id, `Menambah ${c}: ${nama}`);
    }
    return rec;
  },

  update(c, id, data, skipLog) {
    const items = this._getAll(c);
    const idx = items.findIndex(i => i.id === id);
    if (idx === -1) return null;
    const before = { ...items[idx] };
    items[idx] = { ...items[idx], ...data, updatedAt: new Date().toISOString() };
    this._saveAll(c, items);
    if (!skipLog && c !== 'auditLog' && c !== 'transaksi') {
      const nama = items[idx].nama || items[idx].keterangan || id;
      this.logActivity('UBAH', c, id, `Mengubah ${c}: ${nama}`);
    }
    return items[idx];
  },

  delete(c, id, skipLog) {
    const items = this._getAll(c);
    const rec = items.find(i => i.id === id);
    const filtered = items.filter(i => i.id !== id);
    this._saveAll(c, filtered);
    if (!skipLog && rec && c !== 'auditLog' && c !== 'transaksi') {
      const nama = rec.nama || rec.keterangan || id;
      this.logActivity('HAPUS', c, id, `Menghapus ${c}: ${nama}`);
    }
    return filtered.length < items.length;
  },

  // === Settings ===
  getSetting(key, def = null) {
    try { const s = JSON.parse(localStorage.getItem(this.PREFIX + 'settings')) || {}; return s[key] !== undefined ? s[key] : def; }
    catch { return def; }
  },
  setSetting(key, val) {
    const s = JSON.parse(localStorage.getItem(this.PREFIX + 'settings') || '{}');
    s[key] = val;
    localStorage.setItem(this.PREFIX + 'settings', JSON.stringify(s));
  },

  // === Audit Log — Human-Readable ===
  logActivity(action, collection, recordId, deskripsi) {
    const log = this._getAll('auditLog');
    // Try get operator from auth session first
    let operator = this.getSetting('operator', 'System');
    try {
      const session = JSON.parse(localStorage.getItem('sukawarga10_session'));
      if (session?.namaLengkap) operator = session.namaLengkap;
    } catch (e) { }
    log.push({
      id: this.generateId(),
      tanggal: new Date().toISOString(),
      aksi: action, collection, recordId,
      deskripsi: deskripsi || `${action} ${collection}`,
      operator
    });
    if (log.length > 500) log.splice(0, log.length - 500);
    this._saveAll('auditLog', log);
  },

  // === Transaksi — SINGLE SOURCE OF TRUTH ===
  getNextRefNo(prefix) {
    const today = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    const tahun = this.getSetting('tahun', 2026);
    const all = this.getAll('transaksi');
    if (prefix === 'TRX') {
      const existing = all.filter(t => t.refNo && t.refNo.startsWith(`TRX-${today}-`));
      const next = existing.length + 1;
      return `TRX-${today}-${String(next).padStart(3, '0')}`;
    }
    if (prefix === 'KWT') {
      const existing = all.filter(t => t.kwitansiNo && t.kwitansiNo.startsWith(`KWT-${tahun}-`));
      const nums = existing.map(t => parseInt(t.kwitansiNo.split('-')[2]) || 0);
      return `KWT-${tahun}-${String((Math.max(0, ...nums) + 1)).padStart(3, '0')}`;
    }
    if (prefix === 'BKK') {
      const existing = all.filter(t => t.refNo && t.refNo.startsWith(`BKK-${tahun}-`));
      const nums = existing.map(t => parseInt(t.refNo.split('-')[2]) || 0);
      return `BKK-${tahun}-${String((Math.max(0, ...nums) + 1)).padStart(3, '0')}`;
    }
    return this.generateId();
  },

  addTransaksi(data) {
    const refNo = data.refNo || this.getNextRefNo(data.jenis === 'keluar' ? 'BKK' : 'TRX');
    return this.insert('transaksi', {
      tanggal: data.tanggal || new Date().toISOString().slice(0, 10),
      jenis: data.jenis,
      kas: data.kas,
      kategori: data.kategori || '',
      keterangan: data.keterangan || '',
      jumlah: data.jumlah || 0,
      refKeluargaId: data.refKeluargaId || '',
      refNo,
      kwitansiNo: data.kwitansiNo || '',
      operator: this.getSetting('operator', 'Admin'),
      voided: false
    }, true);
  },

  getTransaksi(filter = {}) {
    let trx = this.getAll('transaksi').filter(t => !t.voided);
    if (filter.kas) trx = trx.filter(t => t.kas === filter.kas);
    if (filter.bulan) trx = trx.filter(t => t.tanggal && t.tanggal.slice(5, 7) === filter.bulan);
    if (filter.tahun) trx = trx.filter(t => t.tanggal && t.tanggal.startsWith(String(filter.tahun)));
    if (filter.refKeluargaId) trx = trx.filter(t => t.refKeluargaId === filter.refKeluargaId);
    return trx.sort((a, b) => (a.tanggal || '').localeCompare(b.tanggal || '') || (a.createdAt || '').localeCompare(b.createdAt || ''));
  },

  // Compute saldo from transaksi — NEVER store saldo
  getSaldo(kas, tahun) {
    const trx = this.getTransaksi({ kas, tahun });
    let masuk = 0, keluar = 0;
    trx.forEach(t => { if (t.jenis === 'masuk') masuk += t.jumlah; else keluar += t.jumlah; });
    return { masuk, keluar, saldo: masuk - keluar };
  },

  // === Billing Status Grid ===
  getIuranSampah(keluargaId, tahun) {
    return this.query('iuranSampah', r => r.keluargaId === keluargaId && r.tahun === tahun)[0] || null;
  },
  getIuranPadaringan(keluargaId, tahun) {
    return this.query('iuranPadaringan', r => r.keluargaId === keluargaId && r.tahun === tahun)[0] || null;
  },

  // status: 'lunas' | 'dispensasi' | 'belum'
  setIuranSampahMinggu(keluargaId, tahun, minggu, status, tanggalBayar) {
    const tarif = this.getSetting('tarifSampah', 25000);
    const kk = this.getById('keluarga', keluargaId);
    let record = this.getIuranSampah(keluargaId, tahun);
    const prevStatus = this._getSampahStatus(record, minggu);
    if (prevStatus === status) return record;
    // Determine jumlah stored: lunas=tarif, dispensasi=-1, belum=0
    const val = status === 'lunas' ? tarif : (status === 'dispensasi' ? -1 : 0);
    const tglBayar = tanggalBayar || new Date().toISOString().slice(0, 10);
    if (!record) {
      const weeks = {}; weeks[minggu] = val;
      const weekDates = {}; if (status === 'lunas') weekDates[minggu] = tglBayar;
      record = this.insert('iuranSampah', { keluargaId, tahun, weeks, weekDates }, true);
    } else {
      if (!record.weeks) record.weeks = {};
      if (!record.weekDates) record.weekDates = {};
      record.weeks[minggu] = val;
      if (status === 'lunas') record.weekDates[minggu] = tglBayar;
      else delete record.weekDates[minggu];
      this.update('iuranSampah', record.id, { weeks: record.weeks, weekDates: record.weekDates }, true);
    }
    // Create transaksi only for real money movements
    const nama = kk?.nama || keluargaId;
    if (status === 'lunas' && prevStatus !== 'lunas') {
      const kwtNo = this.getNextRefNo('KWT');
      this.addTransaksi({ tanggal: tglBayar, jenis: 'masuk', kas: 'sampah', kategori: 'iuran', keterangan: `Iuran Sampah ${minggu} — ${nama}`, jumlah: tarif, refKeluargaId: keluargaId, kwitansiNo: kwtNo });
      this.logActivity('BAYAR', 'iuranSampah', keluargaId, `${nama} membayar Iuran Sampah ${minggu} (${kwtNo})`);
    } else if (prevStatus === 'lunas') {
      this.addTransaksi({ jenis: 'keluar', kas: 'sampah', kategori: 'koreksi', keterangan: `Koreksi Iuran Sampah ${minggu} — ${nama}`, jumlah: tarif, refKeluargaId: keluargaId });
      this.logActivity('KOREKSI', 'iuranSampah', keluargaId, `Koreksi ${nama} — Iuran Sampah ${minggu}`);
    } else if (status === 'dispensasi') {
      this.logActivity('DISPENSASI', 'iuranSampah', keluargaId, `Dispensasi ${nama} — Iuran Sampah ${minggu}`);
    }
    return record;
  },

  // Helper: resolve sampah status from stored value
  _getSampahStatus(record, minggu) {
    const v = record?.weeks?.[minggu];
    if (!v || v === 0) return 'belum';
    if (v < 0) return 'dispensasi';
    return 'lunas';
  },

  // Backward-compat toggle (boolean → 3-way cycle)
  toggleIuranSampah(keluargaId, tahun, minggu) {
    const record = this.getIuranSampah(keluargaId, tahun);
    const cur = this._getSampahStatus(record, minggu);
    const next = cur === 'belum' ? 'lunas' : (cur === 'lunas' ? 'dispensasi' : 'belum');
    return this.setIuranSampahMinggu(keluargaId, tahun, minggu, next, null);
  },

  setIuranPadaringanBulan(keluargaId, tahun, bulan, paid, nominal, tanggalBayar) {
    const tarif = this.getSetting('tarifPadaringan', 15000);
    const amount = (nominal && nominal > 0) ? nominal : tarif;
    const kk = this.getById('keluarga', keluargaId);
    let record = this.getIuranPadaringan(keluargaId, tahun);
    const wasPaid = record?.months?.[bulan] > 0;
    if (paid === wasPaid && paid && record?.months?.[bulan] === amount) return record;
    const tglBayar = tanggalBayar || new Date().toISOString().slice(0, 10);
    if (!record) {
      const months = {}; months[bulan] = paid ? amount : 0;
      const monthDates = {}; if (paid) monthDates[bulan] = tglBayar;
      record = this.insert('iuranPadaringan', { keluargaId, tahun, months, monthDates }, true);
    } else {
      if (!record.months) record.months = {};
      if (!record.monthDates) record.monthDates = {};
      record.months[bulan] = paid ? amount : 0;
      if (paid) record.monthDates[bulan] = tglBayar;
      else delete record.monthDates[bulan];
      this.update('iuranPadaringan', record.id, { months: record.months, monthDates: record.monthDates }, true);
    }
    const nama = kk?.nama || keluargaId;
    if (paid) {
      const kwtNo = this.getNextRefNo('KWT');
      const lebih = amount > tarif ? ` (lebih Rp ${(amount - tarif).toLocaleString('id-ID')})` : '';
      this.addTransaksi({ tanggal: tglBayar, jenis: 'masuk', kas: 'padaringan', kategori: 'iuran', keterangan: `Iuran Padaringan ${bulan} — ${nama}${lebih}`, jumlah: amount, refKeluargaId: keluargaId, kwitansiNo: kwtNo });
      this.logActivity('BAYAR', 'iuranPadaringan', keluargaId, `${nama} membayar Iuran Padaringan ${bulan} Rp ${amount.toLocaleString('id-ID')} (${kwtNo})`);
    } else {
      const prevAmount = wasPaid ? (record?.months?.[bulan] || tarif) : tarif;
      this.addTransaksi({ jenis: 'keluar', kas: 'padaringan', kategori: 'koreksi', keterangan: `Koreksi Iuran Padaringan ${bulan} — ${nama}`, jumlah: amount || tarif, refKeluargaId: keluargaId });
      this.logActivity('KOREKSI', 'iuranPadaringan', keluargaId, `Koreksi ${nama} — Iuran Padaringan ${bulan}`);
    }
    return record;
  },

  // === Week-to-Month Mapping ===
  // Returns array of week keys for a month: ['M1','M2','M3'] or ['M1','M2','M3','M4','M5']
  getWeeksInMonth(monthIdx, year) {
    const yr = year || new Date().getFullYear();
    // Count Sundays (or Mondays) that start a new week in the month
    // Use calendar: 1st day of month to last day, count how many distinct ISO weeks fall in it
    const firstDay = new Date(yr, monthIdx, 1);
    const lastDay = new Date(yr, monthIdx + 1, 0);
    // Week 1 of month = week containing the 1st
    // A new week starts each Monday (ISO)
    let count = 0;
    // Simple: count Mondays that are within the month, plus add 1 for week containing the 1st
    // Actually just count: ceil((firstDayOfWeek_offset + totalDays) / 7)
    const dayOfWeekFirst = (firstDay.getDay() + 6) % 7; // 0=Mon, 6=Sun
    const totalDays = lastDay.getDate();
    count = Math.ceil((dayOfWeekFirst + totalDays) / 7);
    // Build keys array ['M1',..,'Mn']
    const keys = [];
    for (let i = 1; i <= count; i++) keys.push('M' + i);
    return { count, keys, start: 1, end: count };
  },

  // Current week-of-month key for a given date (e.g. 'M2' for 2nd week of month)
  weekKey(date) {
    const d = date || new Date();
    const monthIdx = d.getMonth();
    const yr = d.getFullYear();
    const firstDay = new Date(yr, monthIdx, 1);
    const dayOfWeekFirst = (firstDay.getDay() + 6) % 7; // 0=Mon
    const dayOfMonth = d.getDate();
    const weekNum = Math.ceil((dayOfMonth + dayOfWeekFirst) / 7);
    return 'M' + weekNum;
  },

  // === Stats — ALL from transaksi ===
  getStats() {
    const keluarga = this.getAll('keluarga');
    const tahun = this.getSetting('tahun', 2026);
    const now = new Date();
    const monthIdx = now.getMonth();
    const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];

    // Saldo from TRANSAKSI only — 3 kas terpisah
    const saldoSampah = this.getSaldo('sampah', tahun);
    const saldoPadaringan = this.getSaldo('padaringan', tahun);
    const saldoUmum = this.getSaldo('umum', tahun);

    const aktifKK = keluarga.filter(k => (k.status || 'aktif') === 'aktif');
    const ikutSampah = aktifKK.filter(k => k.ikutSampah !== false);
    const ikutPadaringan = aktifKK.filter(k => k.ikutPadaringan !== false);

    // Belum bayar bulan ini
    const { keys: weekKeys } = this.getWeeksInMonth(monthIdx, tahun);
    let belumBayarSampah = 0, belumBayarPadaringan = 0;
    ikutSampah.forEach(kk => {
      const s = this.getIuranSampah(kk.id, tahun);
      let paid = 0;
      weekKeys.forEach(wk => { if (s?.weeks?.[wk] > 0) paid++; });
      if (paid === 0) belumBayarSampah++;
    });
    ikutPadaringan.forEach(kk => {
      const p = this.getIuranPadaringan(kk.id, tahun);
      if (!p?.months?.[monthKeys[monthIdx]] || p.months[monthKeys[monthIdx]] <= 0) belumBayarPadaringan++;
    });

    // Aduan belum selesai
    const aduanBelumSelesai = this.query('aduan', a => a.status !== 'selesai' && a.status !== 'ditolak').length;

    // Days left in month
    const daysLeft = new Date(now.getFullYear(), monthIdx + 1, 0).getDate() - now.getDate();

    return {
      totalKK: keluarga.length,
      wargaAktif: aktifKK.length,
      wargaPindah: keluarga.filter(k => k.status === 'pindah').length,
      wargaBansos: keluarga.filter(k => (k.tags || []).includes('bansos')).length,
      wargaRentan: keluarga.filter(k => { const t = k.tags || []; return t.includes('lansia') || t.includes('difabel') || t.includes('prioritas'); }).length,
      ikutSampah: ikutSampah.length,
      ikutPadaringan: ikutPadaringan.length,
      // From TRANSAKSI — 3 kas
      saldoSampah: saldoSampah.saldo,
      saldoPadaringan: saldoPadaringan.saldo,
      saldoUmum: saldoUmum.saldo,
      saldoTotal: saldoSampah.saldo + saldoPadaringan.saldo + saldoUmum.saldo,
      totalMasukSampah: saldoSampah.masuk,
      totalKeluarSampah: saldoSampah.keluar,
      totalMasukPadaringan: saldoPadaringan.masuk,
      totalKeluarPadaringan: saldoPadaringan.keluar,
      belumBayarSampah, belumBayarPadaringan,
      aduanBelumSelesai,
      daysLeft,
      perRT: this._statsPerRT(keluarga, tahun)
    };
  },

  _statsPerRT(keluarga, tahun) {
    const rts = {};
    const tarifS = this.getSetting('tarifSampah', 5000);
    const tarifP = this.getSetting('tarifPadaringan', 5000);
    keluarga.filter(k => (k.status || 'aktif') === 'aktif').forEach(kk => {
      const rt = kk.rt || 'Lainnya';
      if (!rts[rt]) rts[rt] = { rt, totalKK: 0, sampah: 0, padaringan: 0, kkLunasSampah: 0, kkLunasPadaringan: 0 };
      rts[rt].totalKK++;
      const s = this.getIuranSampah(kk.id, tahun);
      if (s?.weeks) {
        const paid = Object.values(s.weeks).reduce((sum, v) => sum + (v || 0), 0);
        rts[rt].sampah += paid;
        if (paid >= tarifS * 52) rts[rt].kkLunasSampah++;
      }
      const p = this.getIuranPadaringan(kk.id, tahun);
      if (p?.months) {
        const paid = Object.values(p.months).reduce((sum, v) => sum + (v || 0), 0);
        rts[rt].padaringan += paid;
        if (paid >= tarifP * 12) rts[rt].kkLunasPadaringan++;
      }
    });
    return Object.values(rts).sort((a, b) => a.rt.localeCompare(b.rt));
  },

  // === Rekonsiliasi Bulanan ===
  getRekonsiliasi(bulan, tahun) {
    const monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
    const monthIdx = monthKeys.indexOf(bulan);
    const bulanPad = String(monthIdx + 1).padStart(2, '0');
    const keluarga = this.getAll('keluarga').filter(k => (k.status || 'aktif') === 'aktif');
    const ikutS = keluarga.filter(k => k.ikutSampah !== false);
    const ikutP = keluarga.filter(k => k.ikutPadaringan !== false);
    const tarifS = this.getSetting('tarifSampah', 25000);
    const tarifP = this.getSetting('tarifPadaringan', 15000);
    const { count: weeksInMonth } = this.getWeeksInMonth(monthIdx, tahun);

    // Tagihan accurate
    const tagihanSampah = tarifS * weeksInMonth * ikutS.length;
    const tagihanPadaringan = tarifP * ikutP.length;

    // From transaksi
    const trxBulan = this.getTransaksi({ tahun }).filter(t => t.tanggal && t.tanggal.slice(5, 7) === bulanPad);
    const masukS = trxBulan.filter(t => t.kas === 'sampah' && t.jenis === 'masuk').reduce((s, t) => s + t.jumlah, 0);
    const keluarS = trxBulan.filter(t => t.kas === 'sampah' && t.jenis === 'keluar').reduce((s, t) => s + t.jumlah, 0);
    const masukP = trxBulan.filter(t => t.kas === 'padaringan' && t.jenis === 'masuk').reduce((s, t) => s + t.jumlah, 0);
    const keluarP = trxBulan.filter(t => t.kas === 'padaringan' && t.jenis === 'keluar').reduce((s, t) => s + t.jumlah, 0);

    return {
      bulan, tahun,
      sampah: { tagihan: tagihanSampah, terbayar: masukS, outstanding: tagihanSampah - masukS, pengeluaran: keluarS, saldo: masukS - keluarS, kepatuhan: tagihanSampah ? Math.round(masukS / tagihanSampah * 100) : 0 },
      padaringan: { tagihan: tagihanPadaringan, terbayar: masukP, outstanding: tagihanPadaringan - masukP, pengeluaran: keluarP, saldo: masukP - keluarP, kepatuhan: tagihanPadaringan ? Math.round(masukP / tagihanPadaringan * 100) : 0 }
    };
  },

  // === Data Migration ===
  migrateData() {
    const keluarga = this._getAll('keluarga');
    let migrated = false;
    keluarga.forEach(kk => {
      if (kk.status === undefined) { kk.status = 'aktif'; migrated = true; }
      if (!Array.isArray(kk.tags)) { kk.tags = []; migrated = true; }
      if (kk.catatan === undefined) { kk.catatan = ''; migrated = true; }
      if (kk.ikutSampah === undefined) { kk.ikutSampah = true; migrated = true; }
      if (kk.ikutPadaringan === undefined) { kk.ikutPadaringan = true; migrated = true; }
    });
    if (migrated) this._saveAll('keluarga', keluarga);
  },

  clearAll() {
    Object.keys(localStorage).filter(k => k.startsWith(this.PREFIX)).forEach(k => localStorage.removeItem(k));
  },

  exportCSV(rows, filename) {
    if (!rows.length) return;
    const headers = Object.keys(rows[0]);
    const csv = [headers.join(','), ...rows.map(r => headers.map(h => {
      let v = r[h] ?? '';
      if (typeof v === 'object') v = JSON.stringify(v);
      return `"${String(v).replace(/"/g, '""')}"`;
    }).join(','))].join('\n');
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
  },

  // === Nomor Surat Auto ===
  getNextNomorSurat(kodeSurat, tahun) {
    const surat = this.getAll('surat').filter(s => s.kodeSurat === kodeSurat && s.tahun === tahun);
    const nums = surat.map(s => parseInt(s.nomorUrut) || 0);
    const next = (Math.max(0, ...nums) + 1);
    const bulanRomawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][new Date().getMonth()];
    return { nomorUrut: next, nomorSurat: `${kodeSurat}/${String(next).padStart(3, '0')}/RW10/${bulanRomawi}/${tahun}` };
  },

  // === Seed Demo Data ===
  seedDemoData() {
    if (this.count('keluarga') > 0) { this.migrateData(); return; }

    this.setSetting('tarifSampah', 25000);
    this.setSetting('tarifPadaringan', 15000);
    this.setSetting('tahun', 2026);
    this.setSetting('rw', '10');
    this.setSetting('kelurahan', 'Sukakarya');
    this.setSetting('kecamatan', 'Tarogong Kidul');
    this.setSetting('bendahara', 'H. Ano');
    this.setSetting('operator', 'Admin');

    const wargaData = [
      { nama: 'Didi', noKK: '3205053112071556', nik: '3205051507680001', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '', pekerjaan: 'Tidak Bekerja', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 3, status: 'aktif', tags: ['lansia'], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Wahidin', noKK: '3205050706130002', nik: '3205051702890003', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '089688528568', pekerjaan: 'Buruh', penghasilan: '1 - 2.5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Ena Juarna', noKK: '3205050708150017', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '0895700990048', pekerjaan: 'Buruh', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 5, status: 'aktif', tags: ['bansos'], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Jejen', noKK: '3205053112071549', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '089668868315', pekerjaan: 'Petani', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 2, status: 'aktif', tags: ['lansia'], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Dali Hermawan', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '', pekerjaan: 'Buruh', penghasilan: '1 - 2.5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Iwan Hermawan', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '', pekerjaan: 'Buruh', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 3, status: 'aktif', tags: ['bansos'], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Ade Saepudin', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '', pekerjaan: 'Buruh', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 5, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Yoyon Sumpena', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '', pekerjaan: 'Petani', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Heri Heriyanto', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '', pekerjaan: 'Buruh', penghasilan: '1 - 2.5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 3, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Apin Arifin', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '', pekerjaan: 'Buruh', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Yati Mulyati', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '', pekerjaan: 'Tidak Bekerja', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 3, status: 'aktif', tags: ['janda', 'bansos'], ikutSampah: true, ikutPadaringan: true },
      { nama: 'H. Ano', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '', pekerjaan: 'Wiraswasta', penghasilan: '> 5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 5, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Ade Zaenudin', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '', pekerjaan: 'Wiraswasta', penghasilan: '2.5 - 5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Arif', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '', pekerjaan: 'Karyawan Swasta', penghasilan: '2.5 - 5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 3, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Asep Saepuloh', alamat: 'Kp Besarmanah RT 01/RW 10', rt: 'RT 01', noHP: '', pekerjaan: 'Wiraswasta', penghasilan: '2.5 - 5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      // RT 02
      { nama: 'Aip Hidayatulloh', alamat: 'Kp Besarmanah RT 02/RW 10', rt: 'RT 02', noHP: '', pekerjaan: 'Buruh', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: ['bansos'], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Yusup', alamat: 'Kp Besarmanah RT 02/RW 10', rt: 'RT 02', noHP: '', pekerjaan: 'Buruh', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 3, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Komara Herdiana', alamat: 'Kp Besarmanah RT 02/RW 10', rt: 'RT 02', noHP: '', pekerjaan: 'Buruh', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 5, status: 'aktif', tags: ['difabel'], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Rahmat Guanwan', alamat: 'Kp Besarmanah RT 02/RW 10', rt: 'RT 02', noHP: '', pekerjaan: 'Wiraswasta', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Ujang Heryanto', alamat: 'Kp Besarmanah RT 02/RW 10', rt: 'RT 02', noHP: '', pekerjaan: 'Wiraswasta', penghasilan: '1 - 2.5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 3, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Dede Hidayat', alamat: 'Kp Besarmanah RT 02/RW 10', rt: 'RT 02', noHP: '', pekerjaan: 'Karyawan Swasta', penghasilan: '2.5 - 5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Ahmad Sopian', alamat: 'Kp Besarmanah RT 02/RW 10', rt: 'RT 02', noHP: '', pekerjaan: 'Wiraswasta', penghasilan: '2.5 - 5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 5, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Rudi Hartono', alamat: 'Kp Besarmanah RT 02/RW 10', rt: 'RT 02', noHP: '', pekerjaan: 'Buruh', penghasilan: '1 - 2.5 Juta', statusRumah: 'Kontrak', jumlahAnggota: 3, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Toto Supriatna', alamat: 'Kp Besarmanah RT 02/RW 10', rt: 'RT 02', noHP: '', pekerjaan: 'PNS/TNI/Polri', penghasilan: '> 5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      // RT 03
      { nama: 'Nani Sumarni', alamat: 'Kp Besarmanah RT 03/RW 10', rt: 'RT 03', noHP: '', pekerjaan: 'Tidak Bekerja', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 2, status: 'aktif', tags: ['janda', 'lansia', 'bansos'], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Agus Ukon', alamat: 'Kp Besarmanah RT 03/RW 10', rt: 'RT 03', noHP: '', pekerjaan: 'Buruh', penghasilan: '< 1 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Euis Komariah', alamat: 'Kp Besarmanah RT 03/RW 10', rt: 'RT 03', noHP: '', pekerjaan: 'Wiraswasta', penghasilan: '1 - 2.5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 3, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Cucu Hermawan', alamat: 'Kp Besarmanah RT 03/RW 10', rt: 'RT 03', noHP: '', pekerjaan: 'Karyawan Swasta', penghasilan: '2.5 - 5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 5, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Imas Masitoh', alamat: 'Kp Besarmanah RT 03/RW 10', rt: 'RT 03', noHP: '', pekerjaan: 'Wiraswasta', penghasilan: '1 - 2.5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 3, status: 'aktif', tags: ['janda'], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Asep Sunarya', alamat: 'Kp Besarmanah RT 03/RW 10', rt: 'RT 03', noHP: '', pekerjaan: 'Wiraswasta', penghasilan: '2.5 - 5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Enung Nurjanah', alamat: 'Kp Besarmanah RT 03/RW 10', rt: 'RT 03', noHP: '', pekerjaan: 'Tidak Bekerja', penghasilan: '< 1 Juta', statusRumah: 'Menumpang', jumlahAnggota: 2, status: 'aktif', tags: ['bansos', 'prioritas'], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Maman Sulaeman', alamat: 'Kp Besarmanah RT 03/RW 10', rt: 'RT 03', noHP: '', pekerjaan: 'PNS/TNI/Polri', penghasilan: '> 5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 5, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Tatang Suherman', alamat: 'Kp Besarmanah RT 03/RW 10', rt: 'RT 03', noHP: '', pekerjaan: 'Wiraswasta', penghasilan: '2.5 - 5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Siti Aisyah', alamat: 'Kp Besarmanah RT 03/RW 10', rt: 'RT 03', noHP: '', pekerjaan: 'Wiraswasta', penghasilan: '1 - 2.5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 3, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
      { nama: 'Ujang Suhendar', alamat: 'Kp Besarmanah RT 03/RW 10', rt: 'RT 03', noHP: '', pekerjaan: 'Karyawan Swasta', penghasilan: '2.5 - 5 Juta', statusRumah: 'Milik Sendiri', jumlahAnggota: 4, status: 'aktif', tags: [], ikutSampah: true, ikutPadaringan: true },
    ];

    wargaData.forEach(w => {
      this.insert('keluarga', {
        nama: w.nama, noKK: w.noKK || '', nik: w.nik || '',
        alamat: w.alamat, rt: w.rt, rw: '10', kelurahan: 'Sukakarya', kecamatan: 'Tarogong Kidul',
        noHP: w.noHP, pekerjaan: w.pekerjaan, penghasilan: w.penghasilan,
        statusRumah: w.statusRumah, jumlahAnggota: w.jumlahAnggota,
        status: w.status, tags: w.tags || [], catatan: '',
        ikutSampah: w.ikutSampah !== false, ikutPadaringan: w.ikutPadaringan !== false
      }, true);
    });

    // Kepala keluarga as anggota
    this.getAll('keluarga').forEach(kk => {
      this.insert('anggota', { keluargaId: kk.id, nama: kk.nama, jenisKelamin: 'L', statusKeluarga: 'Kepala Keluarga', pekerjaan: kk.pekerjaan }, true);
    });

    // Sample payments
    const kList = this.getAll('keluarga');
    kList.filter(k => k.rt === 'RT 01').slice(0, 8).forEach(kk => {
      this.setIuranPadaringanBulan(kk.id, 2026, 'JAN', true);
      this.setIuranPadaringanBulan(kk.id, 2026, 'FEB', true);
    });
    kList.filter(k => k.rt === 'RT 02').slice(0, 5).forEach(kk => {
      this.setIuranPadaringanBulan(kk.id, 2026, 'FEB', true);
    });
    kList.filter(k => k.rt === 'RT 03').slice(0, 10).forEach(kk => {
      this.setIuranPadaringanBulan(kk.id, 2026, 'FEB', true);
    });
    kList.filter(k => k.rt === 'RT 01').slice(0, 5).forEach(kk => {
      this.setIuranSampahMinggu(kk.id, 2026, 'M1', 'lunas');
      this.setIuranSampahMinggu(kk.id, 2026, 'M2', 'lunas');
    });
    kList.filter(k => k.rt === 'RT 03').slice(0, 4).forEach(kk => {
      this.setIuranSampahMinggu(kk.id, 2026, 'M1', 'lunas');
    });

    // Sample pengeluaran (via transaksi)
    this.insert('pengeluaran', { tanggal: '2026-01-19', kategori: 'padaringan', jenis: 'SANTUNAN', keterangan: 'Pembelian papan alm Ma Empon', jumlah: 200000, penerima: 'H. Ano', verifikasi: true }, true);
    this.addTransaksi({ tanggal: '2026-01-19', jenis: 'keluar', kas: 'padaringan', kategori: 'santunan', keterangan: 'Pembelian papan alm Ma Empon', jumlah: 200000 });
    this.insert('pengeluaran', { tanggal: '2026-02-08', kategori: 'padaringan', jenis: 'SANTUNAN', keterangan: 'Santunan warga sakit', jumlah: 50000, penerima: 'Ibu RT 01', verifikasi: true }, true);
    this.addTransaksi({ tanggal: '2026-02-08', jenis: 'keluar', kas: 'padaringan', kategori: 'santunan', keterangan: 'Santunan warga sakit', jumlah: 50000 });
    this.insert('pengeluaran', { tanggal: '2026-02-14', kategori: 'padaringan', jenis: 'PEMBELIAN', keterangan: 'Pembelian 2 lampu posyandu', jumlah: 70000, penerima: 'Asep Saepuloh', verifikasi: true }, true);
    this.addTransaksi({ tanggal: '2026-02-14', jenis: 'keluar', kas: 'padaringan', kategori: 'pembelian', keterangan: 'Pembelian 2 lampu posyandu', jumlah: 70000 });

    console.log('✅ Demo data seeded v3.0:', this.count('keluarga'), 'keluarga,', this.count('transaksi'), 'transaksi');
  },

  // === Aliases for backward compatibility ===
  add(c, data) { return this.insert(c, data); },
  remove(c, id) { return this.delete(c, id); },

  // === Backup System ===
  checkBackup() {
    const last = this.getSetting('lastBackup', null);
    if (!last) return true;
    const daysSince = (Date.now() - new Date(last).getTime()) / (1000 * 60 * 60 * 24);
    return daysSince >= 7;
  },
  markBackup() {
    this.setSetting('lastBackup', new Date().toISOString());
    this.setSetting('lastBackupDate', new Date().toLocaleDateString('id-ID'));
  },

  // === Export / Import (Secured v3.0) ===
  exportAll() {
    const collections = ['keluarga', 'anggota', 'iuranSampah', 'iuranPadaringan', 'transaksi', 'pengeluaran', 'setorSampah', 'sumbangan', 'aduan', 'surat', 'umkm', 'kegiatan', 'users', 'roles', 'auditLog', 'mpwaTemplates', 'mpwaLog'];
    const data = {};
    collections.forEach(c => { data[c] = this._getAll(c); });

    // SECURITY: strip sensitive fields from user records
    if (data.users) {
      data.users = data.users.map(u => {
        const safe = { ...u };
        delete safe.pin; // Never export PINs
        delete safe._pinMigrated;
        return safe;
      });
    }

    // SECURITY: mask API keys in settings
    const settings = JSON.parse(localStorage.getItem(this.PREFIX + 'settings') || '{}');
    const safeSettings = { ...settings };
    if (safeSettings.mpwaApiKey) {
      safeSettings.mpwaApiKey = '***' + (safeSettings.mpwaApiKey || '').slice(-4);
    }
    data._settings = safeSettings;

    data._exportedAt = new Date().toISOString();
    data._version = '3.0';
    return JSON.stringify(data, null, 2);
  },

  importAll(jsonStr) {
    try {
      const data = JSON.parse(jsonStr);

      // SECURITY: validate structure
      if (typeof data !== 'object' || Array.isArray(data)) {
        throw new Error('Invalid backup format');
      }

      const collections = ['keluarga', 'anggota', 'iuranSampah', 'iuranPadaringan', 'transaksi', 'pengeluaran', 'setorSampah', 'sumbangan', 'aduan', 'surat', 'umkm', 'kegiatan', 'roles', 'auditLog', 'mpwaTemplates', 'mpwaLog'];

      collections.forEach(c => {
        if (data[c] && Array.isArray(data[c])) {
          this._saveAll(c, data[c]);
        }
      });

      return true;
    } catch (e) {
      console.error('Import failed:', e);
      return false;
    }
  },

  async syncUp() {
    try {
      const token = localStorage.getItem('sukawarga10_token') || localStorage.getItem('auth_token');
      // Even without token, if API is public for testing we can sync. Let's just pass token if exists.
      const hdrs = { 'Content-Type': 'application/json' };
      if (token) hdrs['Authorization'] = `Bearer ${token}`;

      const kels = this._getAll('keluarga');
      const angs = this._getAll('anggota');
      const samp = this._getAll('iuranSampah');
      const pad = this._getAll('iuranPadaringan');

      fetch('/api/sync/keluarga', {
        method: 'POST',
        headers: hdrs,
        body: JSON.stringify({ keluargas: kels, anggotas: angs, iuran_sampahs: samp, iuran_padaringans: pad })
      }).catch(e => console.log(e));

      const trxs = this._getAll('transaksi');
      fetch('/api/sync/transaksi', {
        method: 'POST',
        headers: hdrs,
        body: JSON.stringify({ transaksis: trxs })
      }).catch(e => console.log(e));
    } catch (e) { console.log('Sync failed:', e); }
  }
};

