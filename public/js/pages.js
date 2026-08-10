/* pages.js — Dashboard & Pendataan Warga */
window.Pages = {
  _detailTab: null,
  _pendataanFilter: { rt: 'semua', status: 'semua', tag: '' },
  _formStep: 1,
  _pendataanQ: '',

  /* ============================================================
     DASHBOARD
     ============================================================ */
  dashboard() {
    const stats = DB.getStats();
    const tahun = DB.getSetting('tahun', 2026);
    const operator = DB.getSetting('operator', 'Admin');
    const allKK = DB.getAll('keluarga').filter(k => k.status !== 'nonaktif');
    const totalKK = allKK.length;

    // Update sidebar/header
    setTimeout(() => {
      const el = document.getElementById('sidebarAvatar'); if (el) el.textContent = (operator || 'A')[0].toUpperCase();
      const un = document.getElementById('sidebarUserName'); if (un) un.textContent = operator;
      const ha = document.getElementById('headerAvatar'); if (ha) ha.textContent = (operator || 'A')[0].toUpperCase();
      const nb = document.getElementById('navBadgeWarga'); if (nb) nb.textContent = totalKK;

      // Count belum bayar minggu ini
      const today2 = new Date();
      const weekNow = DB.weekKey(today2);
      const belumBayarSampah2 = allKK.filter(k => k.ikutSampah !== false).filter(k => {
        const r = DB.getIuranSampah(k.id, tahun);
        return !(r?.weeks?.[weekNow] > 0);
      }).length;
      const nbS = document.getElementById('navBadgeSampah'); if (nbS) nbS.textContent = belumBayarSampah2 || '✓';

      const sub = document.getElementById('pageSubtitle');
      if (sub) {
        const now = new Date();
        sub.textContent = `RW 10 Sukakarya — ${now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}`;
      }
    }, 0);

    // === Alert bar ===
    const today = new Date();
    const weekKey = DB.weekKey(today);
    const ikutSampahKK = allKK.filter(k => k.ikutSampah !== false);
    const belumBayarWeek = ikutSampahKK.filter(k => {
      const r = DB.getIuranSampah(k.id, tahun); return !(r?.weeks?.[weekKey] > 0);
    });

    let alertHtml = '';
    if (belumBayarWeek.length > 0) {
      const byRT = {};
      belumBayarWeek.forEach(k => { byRT[k.rt] = (byRT[k.rt] || 0) + 1; });
      const detail = Object.entries(byRT).sort().map(([rt, n]) => `${rt}: ${n} KK`).join(', ');
      alertHtml = `<div class="alert alert-warn">⚠️ <span><strong>${belumBayarWeek.length} KK belum bayar</strong> iuran sampah minggu ini (${detail}). <span style="text-decoration:underline;cursor:pointer;font-weight:600" onclick="App.navigate('sampah')">Lihat detail →</span></span></div>`;
    }

    // === Saldo from transaksi ===
    const trxAll = DB.getAll('transaksi').filter(t => !t.voided && t.tanggal?.startsWith(String(tahun)));
    const totalMasukSampah = trxAll.filter(t => t.jenis === 'masuk' && t.kas === 'sampah').reduce((s, t) => s + (t.jumlah || 0), 0);
    const totalKeluarSampah = trxAll.filter(t => t.jenis === 'keluar' && t.kas === 'sampah').reduce((s, t) => s + (t.jumlah || 0), 0);
    const saldoSampah = totalMasukSampah - totalKeluarSampah;

    const totalMasukPad = trxAll.filter(t => t.jenis === 'masuk' && t.kas === 'padaringan').reduce((s, t) => s + (t.jumlah || 0), 0);
    const totalKeluarPad = trxAll.filter(t => t.jenis === 'keluar' && t.kas === 'padaringan').reduce((s, t) => s + (t.jumlah || 0), 0);
    const saldoPadaringan = totalMasukPad - totalKeluarPad;

    const totalMasukUmum = trxAll.filter(t => t.jenis === 'masuk' && t.kas === 'umum').reduce((s, t) => s + (t.jumlah || 0), 0);
    const totalKeluarUmum = trxAll.filter(t => t.jenis === 'keluar' && t.kas === 'umum').reduce((s, t) => s + (t.jumlah || 0), 0);
    const saldoUmum = totalMasukUmum - totalKeluarUmum;
    const saldoTotal = saldoSampah + saldoPadaringan + saldoUmum;

    // Format compact (e.g. 1.24jt)
    const fmtCompact = (n) => {
      if (n >= 1_000_000) return (n / 1_000_000).toFixed(2).replace(/\.?0+$/, '') + 'jt';
      if (n >= 1_000) return (n / 1_000).toFixed(0) + 'rb';
      return n.toLocaleString('id-ID');
    };

    // === RT breakdown for current week (Sampah) ===
    const rtList = [...new Set(allKK.map(k => k.rt))].sort();
    const rtSampahData = rtList.map(rt => {
      const kkRT = ikutSampahKK.filter(k => k.rt === rt);
      const bayar = kkRT.filter(k => { const r = DB.getIuranSampah(k.id, tahun); return r?.weeks?.[weekKey] > 0; }).length;
      const total = kkRT.length;
      const pct = total > 0 ? Math.round(bayar / total * 100) : 0;
      const nominal = bayar * DB.getSetting('tarifSampah', 5000);
      const colorClass = pct === 100 ? 'green' : pct >= 80 ? 'gold' : 'red';
      const badgeClass = pct === 100 ? 'badge-green' : pct >= 80 ? 'badge-gold' : 'badge-red';
      return { rt, bayar, total, pct, nominal, colorClass, badgeClass };
    });

    const rtProgressHtml = rtSampahData.map(r => `
      <div class="rt-item">
        <div class="rt-header">
          <span class="rt-label">🏡 ${r.rt}</span>
          <span class="rt-count">${r.bayar}/${r.total} KK <span class="badge ${r.badgeClass}">${r.pct}%</span></span>
        </div>
        <div class="progress-bar"><div class="progress-fill ${r.colorClass}" style="width:${r.pct}%"></div></div>
        <div class="rt-detail">Rp ${r.nominal.toLocaleString('id-ID')} terkumpul · ${r.total - r.bayar === 0 ? 'Lunas! 🎉' : (r.total - r.bayar) + ' KK belum bayar'}</div>
      </div>`).join('');

    const totalTerkumpulWeek = rtSampahData.reduce((s, r) => s + r.nominal, 0);

    // === Semester-based Chart: Jan-Jun or Jul-Dec ===
    const tahunChart = parseInt(tahun);
    const nowMonth = new Date().getMonth(); // 0-indexed
    const semester = nowMonth < 6 ? 1 : 2;
    const startM = semester === 1 ? 0 : 6;
    const endM = semester === 1 ? 5 : 11;
    const semLabel = semester === 1 ? 'Semester 1 (Jan–Jun)' : 'Semester 2 (Jul–Des)';
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    const allTrx = DB.getAll('transaksi').filter(t => !t.voided);
    const months = [];
    let semMasukTotal = 0, semKeluarTotal = 0;
    let semSampah = 0, semPadaringan = 0, semUmum = 0;
    for (let m = startM; m <= endM; m++) {
      const label = monthNames[m];
      const mTrx = allTrx.filter(t => {
        const td = new Date(t.tanggal || t.createdAt || 0);
        return td.getFullYear() === tahunChart && td.getMonth() === m;
      });
      const masuk = mTrx.filter(t => t.jenis === 'masuk').reduce((s, t) => s + (t.jumlah || 0), 0);
      const keluar = mTrx.filter(t => t.jenis === 'keluar').reduce((s, t) => s + (t.jumlah || 0), 0);
      const isCurrent = m === nowMonth && tahunChart === new Date().getFullYear();
      const isFuture = (tahunChart === new Date().getFullYear() && m > nowMonth);
      months.push({ label, masuk, keluar, net: masuk - keluar, isCurrent, isFuture });
      semMasukTotal += masuk; semKeluarTotal += keluar;
      // per-kas
      semSampah += mTrx.filter(t => t.jenis === 'masuk' && t.kas === 'sampah').reduce((s, t) => s + (t.jumlah || 0), 0);
      semPadaringan += mTrx.filter(t => t.jenis === 'masuk' && t.kas === 'padaringan').reduce((s, t) => s + (t.jumlah || 0), 0);
      semUmum += mTrx.filter(t => t.jenis === 'masuk' && t.kas === 'umum').reduce((s, t) => s + (t.jumlah || 0), 0);
    }
    const maxVal = Math.max(...months.map(m => Math.max(m.masuk, m.keluar)), 1);
    const semNet = semMasukTotal - semKeluarTotal;

    const barChartHtml = months.map(m => {
      const hIn = Math.max(4, Math.round(m.masuk / maxVal * 100));
      const hOut = Math.max(4, Math.round(m.keluar / maxVal * 100));
      const opacity = m.isFuture ? 'opacity:0.25' : '';
      return `<div style="display:flex;flex-direction:column;align-items:center;gap:2px;flex:1;${opacity}" title="${m.label}: +Rp ${m.masuk.toLocaleString('id-ID')} / -Rp ${m.keluar.toLocaleString('id-ID')}">
        <div style="display:flex;gap:2px;align-items:flex-end;height:80px;width:100%">
          <div style="flex:1;height:${hIn}%;background:var(--hijau);border-radius:3px 3px 0 0;min-height:3px;transition:height .3s"></div>
          <div style="flex:1;height:${hOut}%;background:var(--merah);border-radius:3px 3px 0 0;min-height:3px;opacity:0.7;transition:height .3s"></div>
        </div>
        <span style="font-size:10px;color:${m.isCurrent ? 'var(--biru);font-weight:700' : 'var(--text3)'}">${m.label}</span>
      </div>`;
    }).join('');

    // Pemasukan & pengeluaran bulan ini
    const curM = new Date().getMonth() + 1;
    const curY = new Date().getFullYear();
    const trxBulanIni = allTrx.filter(t => {
      const td = new Date(t.tanggal || t.createdAt || 0);
      return td.getFullYear() === curY && (td.getMonth() + 1) === curM;
    });
    const masuknIni = trxBulanIni.filter(t => t.jenis === 'masuk').reduce((s, t) => s + (t.jumlah || 0), 0);
    const keluarIni = trxBulanIni.filter(t => t.jenis === 'keluar').reduce((s, t) => s + (t.jumlah || 0), 0);

    // Per-kas bulan ini
    const sampahIni = trxBulanIni.filter(t => t.jenis === 'masuk' && t.kas === 'sampah').reduce((s, t) => s + (t.jumlah || 0), 0);
    const padIni = trxBulanIni.filter(t => t.jenis === 'masuk' && t.kas === 'padaringan').reduce((s, t) => s + (t.jumlah || 0), 0);
    const umumIni = trxBulanIni.filter(t => t.jenis === 'masuk' && t.kas === 'umum').reduce((s, t) => s + (t.jumlah || 0), 0);

    // === Recent Transactions ===
    const recentTrx = DB.getAll('transaksi').sort((a, b) => new Date(b.tanggal || b.createdAt || 0) - new Date(a.tanggal || a.createdAt || 0)).slice(0, 8);
    const recentHtml = recentTrx.length ? recentTrx.map(t => {
      const kk = t.refKeluargaId ? DB.getById('keluarga', t.refKeluargaId) : null;
      const nama = kk?.nama || t.keterangan?.split('—')[1]?.trim() || t.keterangan || 'Sistem';
      const rt = kk?.rt || '—';
      const dateStr = t.tanggal ? new Date(t.tanggal).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
      const kat = t.kategori || t.jenis;
      const jumlahColor = t.jenis === 'masuk' ? 'color:var(--hijau)' : 'color:var(--merah)';
      const sign = t.jenis === 'masuk' ? '+' : '-';
      const rtBadge = rt !== '—' ? `<span class="badge badge-blue">${rt}</span>` : `<span class="badge badge-gray">RW</span>`;
      const statusBadge = t.jenis === 'masuk' ? '<span class="badge badge-green">Masuk</span>' : '<span class="badge badge-red">Keluar</span>';
      return `<tr>
        <td>${dateStr}</td>
        <td style="font-weight:600;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${nama}</td>
        <td>${rtBadge}</td>
        <td style="text-transform:capitalize">${kat}</td>
        <td class="td-mono" style="${jumlahColor}">${sign}${t.jumlah?.toLocaleString('id-ID') || 0}</td>
        <td>${statusBadge}</td>
      </tr>`;
    }).join('') : `<tr><td colspan="6" style="text-align:center;color:var(--text3);padding:20px">Belum ada transaksi — <button class="btn btn-sm btn-outline" onclick="App.showSettings()">Load Demo Data</button></td></tr>`;

    // Ikut Padaringan count
    const ikutPadaringan = allKK.filter(k => k.ikutPadaringan !== false).length;

    return `<div class="animate-in">
      ${alertHtml}

      <div class="stats-row">
        <div class="stat-card green" onclick="App.navigate('pendataan')">
          <div class="stat-accent"></div>
          <div class="stat-icon-box">👥</div>
          <div class="stat-label">Total KK</div>
          <div class="stat-value">${totalKK}</div>
          <div class="stat-sub">${allKK.reduce((s, k) => s + (parseInt(k.jumlahAnggota) || 0), 0)} jiwa terdaftar</div>
          <div class="stat-trend neutral">${rtList.length} RT aktif</div>
        </div>
        <div class="stat-card gold" onclick="App.navigate('sampah')">
          <div class="stat-accent"></div>
          <div class="stat-icon-box">🗑️</div>
          <div class="stat-label">Kas Sampah</div>
          <div class="stat-value">${fmtCompact(saldoSampah)}</div>
          <div class="stat-sub">Saldo tersedia</div>
          <div class="stat-trend ${saldoSampah >= 0 ? 'up' : 'down'}">
            ${ikutSampahKK.length - belumBayarWeek.length}/${ikutSampahKK.length} KK bayar minggu ini
          </div>
        </div>
        <div class="stat-card blue" onclick="App.navigate('padaringan')">
          <div class="stat-accent"></div>
          <div class="stat-icon-box">💰</div>
          <div class="stat-label">Kas Padaringan</div>
          <div class="stat-value">${fmtCompact(saldoPadaringan)}</div>
          <div class="stat-sub">Saldo tersedia</div>
          <div class="stat-trend neutral">${ikutPadaringan} KK peserta aktif</div>
        </div>
        <div class="stat-card ${saldoTotal >= 0 ? 'green' : 'red'}" onclick="App.navigate('bukukas')">
          <div class="stat-accent"></div>
          <div class="stat-icon-box">📊</div>
          <div class="stat-label">Total Kas RW</div>
          <div class="stat-value">${fmtCompact(saldoTotal)}</div>
          <div class="stat-sub">Sampah + Padaringan + Umum</div>
          <div class="stat-trend ${masuknIni > keluarIni ? 'up' : 'down'}">
            ${masuknIni > keluarIni ? '↑' : '↓'} Bulan ini: ${fmtCompact(masuknIni - keluarIni)}
          </div>
        </div>
      </div>

      <div class="grid-3-1" style="margin-bottom:16px">
        <div class="card">
          <div class="card-header">
            <div>
              <div class="card-title">Tingkat Pembayaran per RT</div>
              <div class="card-sub">Iuran sampah — ${weekKey.replace('M', 'Minggu ke-')}</div>
            </div>
            <button class="btn btn-sm btn-outline" onclick="App.navigate('sampah')">Detail</button>
          </div>
          ${rtProgressHtml || '<div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-title">Belum ada data</div></div>'}
          <div class="divider"></div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:13px;font-weight:700">Total Terkumpul Minggu Ini</span>
            <span class="td-mono text-green" style="font-size:15px">Rp ${totalTerkumpulWeek.toLocaleString('id-ID')}</span>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <div>
              <div class="card-title">Tren Kas ${tahunChart}</div>
              <div class="card-sub">${semLabel} · Pemasukan vs Pengeluaran</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;font-size:10px">
              <span style="display:flex;align-items:center;gap:3px"><span style="width:8px;height:8px;border-radius:2px;background:var(--hijau)"></span>Masuk</span>
              <span style="display:flex;align-items:center;gap:3px"><span style="width:8px;height:8px;border-radius:2px;background:var(--merah);opacity:0.7"></span>Keluar</span>
            </div>
          </div>
          <div style="display:flex;gap:6px;padding:0 4px;margin-bottom:8px">${barChartHtml}</div>
          <div class="divider"></div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <span style="font-size:12px;font-weight:600">Net ${semLabel.split(' ')[0]} ${semester}</span>
            <span class="td-mono" style="font-size:14px;font-weight:700;color:${semNet >= 0 ? 'var(--hijau)' : 'var(--merah)'}">${semNet >= 0 ? '+' : ''}${fmtCompact(semNet)}</span>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-bottom:10px">
            <div style="text-align:center;padding:6px;background:var(--bg2);border-radius:6px">
              <div style="font-size:10px;color:var(--text3)">🗑️ Sampah</div>
              <div style="font-size:12px;font-weight:700">${fmtCompact(semSampah)}</div>
            </div>
            <div style="text-align:center;padding:6px;background:var(--bg2);border-radius:6px">
              <div style="font-size:10px;color:var(--text3)">💰 Padaringan</div>
              <div style="font-size:12px;font-weight:700">${fmtCompact(semPadaringan)}</div>
            </div>
            <div style="text-align:center;padding:6px;background:var(--bg2);border-radius:6px">
              <div style="font-size:10px;color:var(--text3)">📦 Umum</div>
              <div style="font-size:12px;font-weight:700">${fmtCompact(semUmum)}</div>
            </div>
          </div>
          <div class="divider"></div>
          <div class="list-item">
            <div class="list-avatar" style="background:var(--hijau-pale)">📥</div>
            <div class="list-info">
              <div class="list-name">Pemasukan bulan ini</div>
              <div class="list-meta">🗑️${fmtCompact(sampahIni)} · 💰${fmtCompact(padIni)} · 📦${fmtCompact(umumIni)}</div>
            </div>
            <div class="list-amount pos">+${fmtCompact(masuknIni)}</div>
          </div>
          <div class="list-item">
            <div class="list-avatar" style="background:var(--merah-pale)">📤</div>
            <div class="list-info">
              <div class="list-name">Pengeluaran bulan ini</div>
              <div class="list-meta">Operasional</div>
            </div>
            <div class="list-amount neg">-${fmtCompact(keluarIni)}</div>
          </div>
        </div>
      </div>

      <!-- DECISION-SUPPORT CHARTS ROW -->
      ${(() => {
        // === Build analytics from keluarga data ===
        const penghasilanGroups = { '< 1 Juta': 0, '1 - 2.5 Juta': 0, '2.5 - 5 Juta': 0, '> 5 Juta': 0, 'Tidak diketahui': 0 };
        const rumahStatus = {}; const bangunanStatus = {};
        let pkhCount = 0, bpntCount = 0, bltCount = 0, bpjsFull = 0, bpjsPartial = 0;
        const rentanCounts = {};
        allKK.forEach(kk => {
          penghasilanGroups[kk.penghasilan || 'Tidak diketahui'] = (penghasilanGroups[kk.penghasilan || 'Tidak diketahui'] || 0) + 1;
          if (kk.statusRumah) rumahStatus[kk.statusRumah] = (rumahStatus[kk.statusRumah] || 0) + 1;
          if (kk.kondisiBangunan) bangunanStatus[kk.kondisiBangunan] = (bangunanStatus[kk.kondisiBangunan] || 0) + 1;
          if (kk.pkh === 'Ya') pkhCount++;
          if (kk.bpnt === 'Ya') bpntCount++;
          if (kk.blt === 'Ya') bltCount++;
          if (kk.bpjs === 'Semua Punya') bpjsFull++;
          else if (kk.bpjs === 'Sebagian') bpjsPartial++;
          (kk.rentan || []).forEach(r => { rentanCounts[r] = (rentanCounts[r] || 0) + 1; });
        });
        const maxPH = Math.max(...Object.values(penghasilanGroups), 1);
        const phColors = { '< 1 Juta': 'var(--merah)', '1 - 2.5 Juta': 'var(--emas)', '2.5 - 5 Juta': 'var(--biru)', '> 5 Juta': 'var(--hijau)', 'Tidak diketahui': 'var(--text3)' };

        // Horizontal bar chart for Penghasilan
        const phBars = Object.entries(penghasilanGroups).filter(([k]) => k !== 'Tidak diketahui').map(([label, count]) => {
          const pct = totalKK > 0 ? Math.round(count / totalKK * 100) : 0;
          const w = Math.max(8, Math.round(count / maxPH * 100));
          return `<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
            <span style="font-size:10px;min-width:70px;color:var(--text2);text-align:right">${label}</span>
            <div style="flex:1;height:16px;background:var(--bg2);border-radius:4px;overflow:hidden">
              <div style="height:100%;width:${w}%;background:${phColors[label]};border-radius:4px;transition:width .4s"></div>
            </div>
            <span style="font-size:11px;font-weight:600;min-width:40px">${count} <span style="font-size:9px;color:var(--text3)">(${pct}%)</span></span>
          </div>`;
        }).join('');

        // Bansos coverage bars
        const bansosItems = [
          { label: 'BPJS', count: bpjsFull + bpjsPartial, icon: '🏥', detail: `${bpjsFull} penuh, ${bpjsPartial} sebagian` },
          { label: 'PKH', count: pkhCount, icon: '👨‍👩‍👧‍👦' },
          { label: 'BPNT', count: bpntCount, icon: '🍚' },
          { label: 'BLT', count: bltCount, icon: '💵' },
        ];
        const bansosBars = bansosItems.map(b => {
          const pct = totalKK > 0 ? Math.round(b.count / totalKK * 100) : 0;
          return `<div style="display:flex;align-items:center;gap:6px;margin-bottom:5px">
            <span style="font-size:11px;min-width:44px">${b.icon} ${b.label}</span>
            <div style="flex:1;height:14px;background:var(--bg2);border-radius:4px;overflow:hidden">
              <div style="height:100%;width:${pct}%;background:var(--hijau);border-radius:4px;transition:width .4s"></div>
            </div>
            <span style="font-size:11px;font-weight:600;min-width:55px">${b.count}/${totalKK} <span style="font-size:9px;color:var(--text3)">${pct}%</span></span>
          </div>`;
        }).join('');

        // Rumah donut-style stats
        const rumahItems = Object.entries(rumahStatus).map(([k, v]) => {
          const pct = totalKK > 0 ? Math.round(v / totalKK * 100) : 0;
          const color = k === 'Milik' ? 'var(--hijau)' : k === 'Kontrak' ? 'var(--emas)' : 'var(--merah)';
          return `<div style="text-align:center;padding:6px;background:var(--bg2);border-radius:6px">
            <div style="font-size:18px;font-weight:800;color:${color}">${pct}%</div>
            <div style="font-size:10px;color:var(--text3)">${k} (${v})</div>
          </div>`;
        }).join('');
        const bangunanItems = Object.entries(bangunanStatus).map(([k, v]) => {
          const pct = totalKK > 0 ? Math.round(v / totalKK * 100) : 0;
          return `<div style="font-size:11px;display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid var(--bg2)">
            <span>${k}</span><span style="font-weight:600">${v} (${pct}%)</span>
          </div>`;
        }).join('');

        // Kelompok Rentan
        const rentanBars = Object.entries(rentanCounts).sort((a, b) => b[1] - a[1]).map(([k, v]) => {
          const pct = totalKK > 0 ? Math.round(v / totalKK * 100) : 0;
          return `<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
            <span style="font-size:10px;min-width:100px;color:var(--text2)">${k}</span>
            <div style="flex:1;height:12px;background:var(--bg2);border-radius:3px;overflow:hidden">
              <div style="height:100%;width:${Math.max(6, pct)}%;background:var(--emas);border-radius:3px"></div>
            </div>
            <span style="font-size:10px;font-weight:600">${v}</span>
          </div>`;
        }).join('') || '<div style="font-size:11px;color:var(--text3);text-align:center;padding:8px">Belum ada data rentan</div>';

        return `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">
        <div class="card">
          <div class="card-title">💰 Distribusi Penghasilan Warga</div>
          <div class="card-sub">Pendapatan per bulan — ${totalKK} KK</div>
          <div style="margin-top:8px">${phBars}</div>
          ${penghasilanGroups['Tidak diketahui'] > 0 ? `<div style="font-size:10px;color:var(--text3);margin-top:4px">⚠️ ${penghasilanGroups['Tidak diketahui']} KK belum diisi data penghasilan</div>` : ''}
        </div>
        <div class="card">
          <div class="card-title">🛡️ Cakupan Bansos & BPJS</div>
          <div class="card-sub">Penerima aktif — ${totalKK} KK</div>
          <div style="margin-top:8px">${bansosBars}</div>
        </div>
        <div class="card">
          <div class="card-title">🏠 Status Kepemilikan Rumah</div>
          <div class="card-sub">Tipe kepemilikan & bangunan</div>
          <div style="display:grid;grid-template-columns:repeat(${Object.keys(rumahStatus).length || 1}, 1fr);gap:6px;margin:8px 0">${rumahItems || '<div style="font-size:11px;color:var(--text3);text-align:center">Belum ada data</div>'}</div>
          ${bangunanItems ? `<div style="margin-top:4px"><div style="font-size:11px;font-weight:600;margin-bottom:4px;color:var(--text2)">Tipe Bangunan:</div>${bangunanItems}</div>` : ''}
        </div>
        <div class="card">
          <div class="card-title">⚠️ Kelompok Rentan</div>
          <div class="card-sub">Warga yang perlu perhatian khusus</div>
          <div style="margin-top:8px">${rentanBars}</div>
        </div>
      </div>`;
      })()}

      <div class="card">
        <div class="card-header" style="margin-bottom:4px">
          <div>
            <div class="card-title">Transaksi Terbaru</div>
            <div class="card-sub">8 transaksi terakhir</div>
          </div>
          <button class="btn btn-sm btn-outline" onclick="App.navigate('bukukas')">Lihat Semua</button>
        </div>
        <div class="data-table-wrapper">
          <table class="data-table">
            <thead><tr><th>Tanggal</th><th>Nama / Keterangan</th><th>RT</th><th>Jenis</th><th>Jumlah</th><th>Status</th></tr></thead>
            <tbody>${recentHtml}</tbody>
          </table>
        </div>
      </div>
    </div>`;
  },

  /* ============================================================
     PENDATAAN WARGA
     ============================================================ */
  pendataan() {
    const { rt, status, tag } = this._pendataanFilter;
    const q = this._pendataanQ || '';
    let data = DB.getAll('keluarga');

    // Filters
    if (rt !== 'semua') data = data.filter(k => k.rt === rt);
    if (status !== 'semua') data = data.filter(k => (k.status || 'aktif') === status);
    if (tag) data = data.filter(k => (k.tags || []).includes(tag));
    if (q) data = data.filter(k => (k.nama || '').toLowerCase().includes(q.toLowerCase()) || (k.noHP || '').includes(q) || (k.noKK || '').includes(q));

    const allKK = DB.getAll('keluarga');
    const rtList = [...new Set(allKK.map(k => k.rt))].sort();
    const allTags = [...new Set(allKK.flatMap(k => k.tags || []))].sort();
    const tahun = DB.getSetting('tahun', 2026);

    const getAvatarColor = (nama) => {
      const colors = ['var(--hijau)', '#E84040', 'var(--emas)', 'var(--biru)', 'var(--ungu)', '#0F9D58', '#F4511E', '#7B1FA2'];
      let h = 0; for (const c of (nama || '')) h = (h * 31 + c.charCodeAt(0)) & 0xFFFFFFFF;
      return colors[Math.abs(h) % colors.length];
    };
    const initials = (nama = '') => nama.split(' ').slice(0, 2).map(w => w[0]?.toUpperCase() || '').join('');

    const cardsHtml = data.length ? data.map(kk => {
      const color = getAvatarColor(kk.nama);
      const ini = initials(kk.nama);

      return `<div class="warga-card" onclick="App.showDetailKK('${kk.id}')">
        <div class="warga-header">
          <div class="warga-avatar" style="background:${color}">${ini}</div>
          <div style="min-width:0">
            <div class="warga-name">${kk.nama}</div>
            <div class="warga-kk">${kk.rt} · ${kk.status === 'nonaktif' ? '<span style="color:var(--merah)">Nonaktif</span>' : 'Aktif'}</div>
            ${kk.noKK ? `<div class="warga-nokk">${kk.noKK}</div>` : ''}
          </div>
        </div>
      </div>`;
    }).join('') + (Auth.can('canEdit') ? `
      <div class="warga-card warga-card-add" onclick="Pages.showFormKK()">
        <div style="font-size:32px">➕</div>
        <div style="font-size:13px;font-weight:700;color:var(--text3)">Tambah KK Baru</div>
      </div>` : '')
      : `<div style="grid-column:1/-1"><div class="empty-state"><div class="empty-state-icon">🔍</div><div class="empty-state-title">Tidak ada hasil</div><div class="empty-state-sub">Coba ubah filter pencarian</div></div></div>`;

    return `<div class="animate-in">
      <div class="card" style="margin-bottom:14px">
        <div style="display:grid;grid-template-columns:1fr auto;gap:10px;margin-bottom:12px;align-items:center">
          <div class="search-bar">
            🔍 <input type="text" placeholder="Cari nama atau nomor HP..." value="${q}" oninput="Pages._pendataanQ=this.value;Pages._render()">
          </div>
          <div style="display:flex;gap:8px">
            ${Auth.can('canEdit') ? '<button class="btn btn-outline btn-sm" onclick="Pages._importCSV()">📥 Import CSV</button>' : ''}
            ${Auth.can('canEdit') ? '<button class="btn btn-primary btn-sm" onclick="Pages.showFormKK()">➕ Tambah KK</button>' : ''}
          </div>
        </div>
        <div class="filter-row">
          <div class="filter-chip ${rt === 'semua' ? 'active' : ''}" onclick="Pages._pendataanFilter.rt='semua';Pages._render()">Semua (${allKK.length})</div>
          ${rtList.map(r => `<div class="filter-chip ${rt === r ? 'active' : ''}" onclick="Pages._pendataanFilter.rt='${r}';Pages._render()">${r} (${allKK.filter(k => k.rt === r).length})</div>`).join('')}
          <div class="filter-chip ${status === 'nonaktif' ? 'active' : ''}" onclick="Pages._pendataanFilter.status=Pages._pendataanFilter.status==='nonaktif'?'semua':'nonaktif';Pages._render()">Nonaktif</div>
          ${allTags.slice(0, 4).map(t => `<div class="filter-chip ${tag === t ? 'active' : ''}" onclick="Pages._pendataanFilter.tag=Pages._pendataanFilter.tag==='${t}'?'':'${t}';Pages._render()">${t}</div>`).join('')}
        </div>
        <div style="font-size:12px;color:var(--text3)">${data.length} KK ditampilkan</div>
      </div>

      <div class="warga-grid">${cardsHtml}</div>
    </div>`;
  },

  _render() {
    const main = document.getElementById('pageContent');
    if (!main) return;
    const page = App.currentPage;
    if (page === 'pendataan') main.innerHTML = this.pendataan();
    else if (page === 'dashboard') main.innerHTML = this.dashboard();
  },

  /* ============================================================
     FORM KK (4-step — sesuai Pendataan Terbaik.xlsx)
     ============================================================ */
  showFormKK(id = null) {
    const kk = id ? DB.getById('keluarga', id) : {};
    const isEdit = !!id;
    this._formStep = 1;

    const allKK = DB.getAll('keluarga');
    const rtList = [...new Set(allKK.map(k => k.rt))].sort();
    const rtOptions = [...new Set([...rtList, 'RT 01', 'RT 02', 'RT 03', 'RT 04'])].sort().map(r => `<option ${kk.rt === r ? 'selected' : ''}>${r}</option>`).join('');
    const jumlahAnggota = id ? DB.getAll('anggota').filter(a => a.keluargaId === id).length || kk.jumlahAnggota || '' : '';
    const chk = (arr, val) => (arr || []).includes(val) ? 'checked' : '';
    const sel = (cur, val) => cur === val ? 'selected' : '';

    const kkFotoPreview = kk.fotoKK ? `<img src="${kk.fotoKK}" style="max-height:80px;border-radius:6px;border:1px solid var(--border);margin-bottom:6px" id="kkFotoPreviewImg">` : '<div id="kkFotoPreviewImg"></div>';

    App.showModal(isEdit ? '✏️ Edit Data KK' : '➕ Tambah KK Baru', `
      <div class="stepper" id="kkFormStepper">
        <div class="stepper-step active" id="step1-ind"><div class="stepper-circle">1</div><div class="stepper-label">Identitas</div></div>
        <div class="stepper-line" id="step-line12"></div>
        <div class="stepper-step" id="step2-ind"><div class="stepper-circle">2</div><div class="stepper-label">Rumah</div></div>
        <div class="stepper-line" id="step-line23"></div>
        <div class="stepper-step" id="step3-ind"><div class="stepper-circle">3</div><div class="stepper-label">Ekonomi</div></div>
        <div class="stepper-line" id="step-line34"></div>
        <div class="stepper-step" id="step4-ind"><div class="stepper-circle">4</div><div class="stepper-label">Bansos</div></div>
      </div>

      <!-- STEP 1: IDENTITAS -->
      <div id="kkFormStep1">
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Nama Kepala Keluarga *</label><input class="form-input" id="fKkNama" value="${kk.nama || ''}" placeholder="Nama lengkap"></div>
          <div class="form-group"><label class="form-label">RT *</label><select class="form-select" id="fKkRT">${rtOptions}</select></div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">No. KK *</label><input class="form-input" id="fKkNoKK" value="${kk.noKK || ''}" maxlength="16" inputmode="numeric" placeholder="16 digit"></div>
          <div class="form-group"><label class="form-label">NIK Kepala Keluarga</label><input class="form-input" id="fKkNIK" value="${kk.nik || ''}" maxlength="16" inputmode="numeric" placeholder="16 digit"></div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">No. HP / WhatsApp</label><input class="form-input" id="fKkHP" value="${kk.noHP || ''}" placeholder="628xxx" type="tel"></div>
          <div class="form-group"><label class="form-label">Jumlah Jiwa</label><input class="form-input" id="fKkJiwa" type="number" min="1" value="${jumlahAnggota}" placeholder="Otomatis dari anggota"></div>
        </div>
        <div class="form-group"><label class="form-label">Alamat</label><input class="form-input" id="fKkAlamat" value="${kk.alamat || ''}" placeholder="Kp/Jl ..."></div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Kelurahan/Kecamatan</label><input class="form-input" id="fKkKelurahan" value="${kk.kelurahan || 'Sukakarya/Tarogong Kidul'}" placeholder="Kelurahan/Kecamatan"></div>
          <div class="form-group"><label class="form-label">Status KK</label>
            <select class="form-select" id="fKkStatus">
              <option value="aktif" ${sel(kk.status || 'aktif', 'aktif')}>Aktif</option>
              <option value="nonaktif" ${sel(kk.status, 'nonaktif')}>Nonaktif (pindah/meninggal)</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">📄 Foto Kartu Keluarga (KK) *</label>
          ${kkFotoPreview}
          <input type="file" accept="image/*" class="form-input" id="fKkFotoKK" onchange="Pages._previewUpload(this,'kkFotoPreviewImg')" style="margin-top:4px">
          <div style="font-size:10px;color:var(--text3);margin-top:2px">Format: JPG/PNG, maks 2MB. Wajib dilampirkan.</div>
        </div>
        <button class="btn btn-primary btn-block" onclick="Pages._kkGoStep(2)">Lanjut → Rumah & Sanitasi</button>
      </div>

      <!-- STEP 2: RUMAH & SANITASI -->
      <div id="kkFormStep2" style="display:none">
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Status Rumah</label>
            <select class="form-select" id="fKkRumah"><option value="">—</option>${['Milik', 'Kontrak', 'Numpang'].map(o => `<option ${sel(kk.statusRumah, o)}>${o}</option>`).join('')}</select>
          </div>
          <div class="form-group"><label class="form-label">Tipe Bangunan</label>
            <select class="form-select" id="fKkKondBangunan"><option value="">—</option>${['Permanen', 'Semi permanen', 'Non permanen'].map(o => `<option ${sel(kk.kondisiBangunan, o)}>${o}</option>`).join('')}</select>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Luas Lantai (m²)</label><input class="form-input" id="fKkLuasLantai" type="number" value="${kk.luasLantai || ''}" placeholder="m²"></div>
          <div class="form-group"><label class="form-label">Jml Kamar Tidur</label><input class="form-input" id="fKkKamarTidur" type="number" value="${kk.kamarTidur || ''}" placeholder="Jumlah"></div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Bahan Lantai</label>
            <select class="form-select" id="fKkBahanLantai"><option value="">—</option>${['Keramik', 'Ubin/Tegel', 'Semen', 'Tanah', 'Kayu'].map(o => `<option ${sel(kk.bahanLantai, o)}>${o}</option>`).join('')}</select>
          </div>
          <div class="form-group"><label class="form-label">Bahan Dinding</label>
            <select class="form-select" id="fKkBahanDinding"><option value="">—</option>${['Tembok diplester', 'Tembok tanpa plester', 'Kayu/Papan', 'Bambu', 'Triplek'].map(o => `<option ${sel(kk.bahanDinding, o)}>${o}</option>`).join('')}</select>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Bahan Atap</label>
            <select class="form-select" id="fKkBahanAtap"><option value="">—</option>${['Genteng', 'Metal/Seng', 'Asbes', 'Bambu/Kayu', 'Daun'].map(o => `<option ${sel(kk.bahanAtap, o)}>${o}</option>`).join('')}</select>
          </div>
          <div class="form-group"><label class="form-label">Sumber Air Minum</label>
            <select class="form-select" id="fKkSumberAir"><option value="">—</option>${['PDAM/PAM', 'Sumur pompa', 'Sumur gali', 'Mata air', 'Air hujan', 'Sungai'].map(o => `<option ${sel(kk.sumberAir, o)}>${o}</option>`).join('')}</select>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Sumber Air Mandi/Cuci</label>
            <select class="form-select" id="fKkSumberAirMandi"><option value="">—</option>${['PDAM/PAM', 'Sumur pompa', 'Sumur gali', 'Sungai', 'Mata air'].map(o => `<option ${sel(kk.sumberAirMandi, o)}>${o}</option>`).join('')}</select>
          </div>
          <div class="form-group"><label class="form-label">Sumber Masak</label>
            <select class="form-select" id="fKkSumberMasak"><option value="">—</option>${['LPG', 'Minyak Tanah', 'Kayu Bakar', 'Biogas', 'Listrik'].map(o => `<option ${sel(kk.sumberMasak, o)}>${o}</option>`).join('')}</select>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Kepemilikan Jamban</label>
            <select class="form-select" id="fKkJamban"><option value="">—</option>${['Jamban sendiri', 'Jamban bersama', 'Tidak ada'].map(o => `<option ${sel(kk.kepemilikanJamban, o)}>${o}</option>`).join('')}</select>
          </div>
          <div class="form-group"><label class="form-label">Pembuangan Tinja</label>
            <select class="form-select" id="fKkPembuanganTinja"><option value="">—</option>${['Septic tank', 'Cubluk', 'Sungai/Laut', 'Kolam/Sawah'].map(o => `<option ${sel(kk.pembuanganTinja, o)}>${o}</option>`).join('')}</select>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Cara Buang Sampah</label>
            <select class="form-select" id="fKkCaraSampah"><option value="">—</option>${['Diangkut petugas', 'Dibakar', 'Lubang/Tanah', 'Sungai', 'Lainnya'].map(o => `<option ${sel(kk.caraSampah, o)}>${o}</option>`).join('')}</select>
          </div>
          <div class="form-group"><label class="form-label">Sumber Listrik</label>
            <select class="form-select" id="fKkListrik"><option value="">—</option>${['PLN (Meteran)', 'PLN (Sambungan Bagi)', 'Solar Cell', 'Tidak Ada'].map(o => `<option ${sel(kk.sumberListrik, o)}>${o}</option>`).join('')}</select>
          </div>
        </div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-outline" onclick="Pages._kkGoStep(1)">← Kembali</button>
          <button class="btn btn-primary" style="flex:1" onclick="Pages._kkGoStep(3)">Lanjut → Ekonomi</button>
        </div>
      </div>

      <!-- STEP 3: EKONOMI & KEUANGAN -->
      <div id="kkFormStep3" style="display:none">
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Pekerjaan</label><input class="form-input" id="fKkPekerjaan" value="${kk.pekerjaan || ''}" placeholder="Wiraswasta, PNS, dll"></div>
          <div class="form-group"><label class="form-label">Sumber Pendapatan</label>
            <select class="form-select" id="fKkSumberPendapatan"><option value="">—</option>${['Upah/Gaji', 'Usaha Sendiri', 'Tani', 'Buruh Harian', 'Tidak Bekerja', 'Lainnya'].map(o => `<option ${sel(kk.sumberPendapatan, o)}>${o}</option>`).join('')}</select>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Penghasilan/Bulan</label>
            <select class="form-select" id="fKkPenghasilan"><option value="">—</option>${['< 1 Juta', '1 - 2.5 Juta', '2.5 - 5 Juta', '> 5 Juta'].map(o => `<option ${sel(kk.penghasilan, o)}>${o}</option>`).join('')}</select>
          </div>
          <div class="form-group"><label class="form-label">Punya Tabungan</label>
            <select class="form-select" id="fKkTabungan"><option value="">—</option>${['Ya', 'Tidak'].map(o => `<option ${sel(kk.tabungan, o)}>${o}</option>`).join('')}</select>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Punya Hutang Usaha</label>
            <select class="form-select" id="fKkHutang"><option value="">—</option>${['Ya', 'Tidak'].map(o => `<option ${sel(kk.hutangUsaha, o)}>${o}</option>`).join('')}</select>
          </div>
          <div class="form-group"><label class="form-label">Akses Kredit</label>
            <select class="form-select" id="fKkAksesKredit"><option value="">—</option>${['KUR', 'Bank Umum', 'Koperasi', 'Rentenir', 'Tidak Ada'].map(o => `<option ${sel(kk.aksesKredit, o)}>${o}</option>`).join('')}</select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Kepemilikan Aset</label>
          <div class="tag-checkbox-group">
            ${['Motor', 'Mobil', 'Tanah', 'Sawah', 'Perahu', 'Ternak', 'Komputer/Laptop'].map(o => `<label class="tag-checkbox"><input type="checkbox" class="fKkAset" value="${o}" ${chk(kk.aset, o)} style="accent-color:var(--hijau)">${o}</label>`).join('')}
          </div>
        </div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-outline" onclick="Pages._kkGoStep(2)">← Kembali</button>
          <button class="btn btn-primary" style="flex:1" onclick="Pages._kkGoStep(4)">Lanjut → Bansos & Catatan</button>
        </div>
      </div>

      <!-- STEP 4: BANSOS & CATATAN -->
      <div id="kkFormStep4" style="display:none">
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">BPJS / JKN</label>
            <select class="form-select" id="fKkBPJS"><option value="">—</option>${['Semua Punya', 'Sebagian', 'Tidak ada'].map(o => `<option ${sel(kk.bpjs, o)}>${o}</option>`).join('')}</select>
          </div>
          <div class="form-group"><label class="form-label">PKH</label>
            <select class="form-select" id="fKkPKH"><option value="">—</option>${['Ya', 'Tidak'].map(o => `<option ${sel(kk.pkh, o)}>${o}</option>`).join('')}</select>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">BPNT / Sembako</label>
            <select class="form-select" id="fKkBPNT"><option value="">—</option>${['Ya', 'Tidak'].map(o => `<option ${sel(kk.bpnt, o)}>${o}</option>`).join('')}</select>
          </div>
          <div class="form-group"><label class="form-label">BLT / Bantuan Tunai</label>
            <select class="form-select" id="fKkBLT"><option value="">—</option>${['Ya', 'Tidak'].map(o => `<option ${sel(kk.blt, o)}>${o}</option>`).join('')}</select>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Rutilahu</label>
            <select class="form-select" id="fKkRutilahu"><option value="">—</option>${['Ya', 'Tidak'].map(o => `<option ${sel(kk.rutilahu, o)}>${o}</option>`).join('')}</select>
          </div>
          <div class="form-group"><label class="form-label">KIS (JKN PBI)</label>
            <select class="form-select" id="fKkKIS"><option value="">—</option>${['Ya', 'Tidak'].map(o => `<option ${sel(kk.kis, o)}>${o}</option>`).join('')}</select>
          </div>
        </div>
        <div class="form-group"><label class="form-label">KIP</label>
          <select class="form-select" id="fKkKIP"><option value="">—</option>${['Ya', 'Tidak'].map(o => `<option ${sel(kk.kip, o)}>${o}</option>`).join('')}</select>
        </div>
        <div class="form-group">
          <label class="form-label">Kelompok Rentan</label>
          <div class="tag-checkbox-group">
            ${['Lansia (≥60th)', 'Balita (0-5th)', 'Ibu Hamil', 'Penyandang Disabilitas', 'Janda/Duda', 'Yatim/Piatu'].map(o => `<label class="tag-checkbox"><input type="checkbox" class="fKkRentan" value="${o}" ${chk(kk.rentan, o)} style="accent-color:var(--hijau)">${o}</label>`).join('')}
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Tag Sosial</label>
          <div class="tag-checkbox-group">
            ${['Kurang Mampu', 'Lansia', 'UMKM', 'Disabilitas', 'Janda/Duda', 'Migran'].map(t => `<label class="tag-checkbox"><input type="checkbox" ${(kk.tags || []).includes(t) ? 'checked' : ''} value="${t}" class="fKkTag" style="accent-color:var(--hijau)">${t}</label>`).join('')}
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Program Iuran</label>
          <div style="display:flex;gap:16px">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" id="fKkIkutSampah" ${kk.ikutSampah !== false ? 'checked' : ''} style="accent-color:var(--hijau)"> Ikut Iuran Sampah</label>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" id="fKkIkutPadaringan" ${kk.ikutPadaringan !== false ? 'checked' : ''} style="accent-color:var(--hijau)"> Ikut Padaringan</label>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Catatan Khusus</label><textarea class="form-textarea" id="fKkCatatan">${kk.catatan || ''}</textarea></div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-outline" onclick="Pages._kkGoStep(3)">← Kembali</button>
          <button class="btn btn-primary" style="flex:1" onclick="Pages._saveKK('${id || ''}')">💾 Simpan Data KK</button>
        </div>
      </div>
    `);
  },

  // 4-step navigation
  _kkGoStep(target) {
    if (target > 1 && !document.getElementById('fKkNama')?.value?.trim()) {
      return App.toast('Nama KK wajib diisi', 'warning');
    }
    for (let i = 1; i <= 4; i++) {
      const el = document.getElementById('kkFormStep' + i);
      if (el) el.style.display = i === target ? 'block' : 'none';
      const ind = document.getElementById('step' + i + '-ind');
      if (ind) { ind.classList.toggle('active', i <= target); ind.classList.toggle('completed', i < target); }
    }
    for (const pair of [[1, 2], [2, 3], [3, 4]]) {
      const line = document.getElementById('step-line' + pair[0] + pair[1]);
      if (line) line.classList.toggle('completed', pair[0] < target);
    }
  },

  _previewUpload(input, previewId) {
    const file = input.files[0]; if (!file) return;
    if (file.size > 2 * 1024 * 1024) { App.toast('Ukuran file maks 2MB', 'error'); input.value = ''; return; }
    const reader = new FileReader();
    reader.onload = (e) => {
      const el = document.getElementById(previewId);
      if (el) { el.outerHTML = `<img src="${e.target.result}" style="max-height:80px;border-radius:6px;border:1px solid var(--border);margin-bottom:6px" id="${previewId}">`; }
    };
    reader.readAsDataURL(file);
  },

  _saveKK(id) {
    const g = (sel) => document.getElementById(sel);
    const gv = (sel) => g(sel)?.value?.trim() || '';
    const gs = (sel) => g(sel)?.value || '';
    const multiChk = (cls) => [...document.querySelectorAll('.' + cls + ':checked')].map(c => c.value);

    let fotoKK = '';
    const fotoPreview = g('kkFotoPreviewImg');
    if (fotoPreview?.src && fotoPreview.src.startsWith('data:')) fotoKK = fotoPreview.src;
    if (!fotoKK && id) { const existing = DB.getById('keluarga', id); fotoKK = existing?.fotoKK || ''; }

    const data = {
      nama: gv('fKkNama'), rt: gs('fKkRT'), noKK: gv('fKkNoKK'), nik: gv('fKkNIK'),
      noHP: gv('fKkHP'), jumlahAnggota: parseInt(g('fKkJiwa')?.value) || 0,
      alamat: gv('fKkAlamat'), kelurahan: gv('fKkKelurahan'), status: gs('fKkStatus'), fotoKK,
      statusRumah: gs('fKkRumah'), kondisiBangunan: gs('fKkKondBangunan'),
      luasLantai: parseInt(g('fKkLuasLantai')?.value) || null, kamarTidur: parseInt(g('fKkKamarTidur')?.value) || null,
      bahanLantai: gs('fKkBahanLantai'), bahanDinding: gs('fKkBahanDinding'), bahanAtap: gs('fKkBahanAtap'),
      sumberAir: gs('fKkSumberAir'), sumberAirMandi: gs('fKkSumberAirMandi'), sumberMasak: gs('fKkSumberMasak'),
      kepemilikanJamban: gs('fKkJamban'), pembuanganTinja: gs('fKkPembuanganTinja'),
      caraSampah: gs('fKkCaraSampah'), sumberListrik: gs('fKkListrik'),
      pekerjaan: gv('fKkPekerjaan'), sumberPendapatan: gs('fKkSumberPendapatan'),
      penghasilan: gs('fKkPenghasilan'), tabungan: gs('fKkTabungan'),
      hutangUsaha: gs('fKkHutang'), aksesKredit: gs('fKkAksesKredit'), aset: multiChk('fKkAset'),
      bpjs: gs('fKkBPJS'), pkh: gs('fKkPKH'), bpnt: gs('fKkBPNT'), blt: gs('fKkBLT'),
      rutilahu: gs('fKkRutilahu'), kis: gs('fKkKIS'), kip: gs('fKkKIP'),
      rentan: multiChk('fKkRentan'), tags: multiChk('fKkTag'),
      ikutSampah: g('fKkIkutSampah')?.checked ?? true, ikutPadaringan: g('fKkIkutPadaringan')?.checked ?? true,
      catatan: gv('fKkCatatan'),
      bansos: [gs('fKkPKH') === 'Ya' && 'PKH', gs('fKkBPNT') === 'Ya' && 'BPNT/Sembako', gs('fKkBLT') === 'Ya' && 'BLT', gs('fKkKIS') === 'Ya' && 'KIS', gs('fKkKIP') === 'Ya' && 'KIP'].filter(Boolean),
    };
    if (!data.nama) { App.toast('Nama wajib diisi', 'warning'); return; }
    if (id) { DB.update('keluarga', id, data); App.toast('✅ Data KK diperbarui', 'success'); }
    else { DB.insert('keluarga', data); App.toast('✅ KK baru ditambahkan', 'success'); }
    App.closeModal();
    this._pendataanFilter = { rt: 'semua', status: 'semua', tag: '' };
    App.render('pendataan');
  },


  /* ============================================================
     CSV IMPORT
     ============================================================ */
  _importCSV() {
    App.showModal('📥 Import Data Warga dari CSV', `
      <p style="font-size:13px;color:var(--text3);margin-bottom:12px">Format CSV: <code>nama,rt,noHP,jumlahAnggota,alamat,pekerjaan,penghasilan</code></p>
      <div style="display:flex;gap:8px;margin-bottom:14px">
        <button class="btn btn-outline btn-sm" onclick="Pages._downloadCSVTemplate()">⬇️ Download Template</button>
      </div>
      <div class="form-group">
        <label class="form-label">Pilih file CSV</label>
        <input type="file" accept=".csv" class="form-input" id="csvFileInput" onchange="Pages._previewCSV(this)">
      </div>
      <div id="csvPreview"></div>
    `);
  },

  _downloadCSVTemplate() {
    const headers = 'nama,rt,noHP,jumlahAnggota,alamat,pekerjaan,penghasilan';
    const sample = 'Asep Suhendar,RT 01,628123456789,4,Jl. Contoh No 1,Wiraswasta,1 - 3 Juta';
    const blob = new Blob([headers + '\n' + sample], { type: 'text/csv' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'template-warga.csv'; a.click();
  },

  _previewCSV(input) {
    const file = input.files[0]; if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      const lines = e.target.result.split('\n').filter(l => l.trim());
      const headers = lines[0].split(',').map(h => h.trim());
      const rows = lines.slice(1).map(l => {
        const vals = l.split(','); return Object.fromEntries(headers.map((h, i) => [h, (vals[i] || '').trim()]));
      }).filter(r => r.nama);
      const preview = document.getElementById('csvPreview');
      preview.innerHTML = `<div class="alert alert-info" style="margin-bottom:8px">✅ ${rows.length} KK siap diimport</div>
        <div class="data-table-wrapper"><table class="data-table"><thead><tr><th>#</th><th>Nama</th><th>RT</th><th>HP</th></tr></thead><tbody>
          ${rows.slice(0, 5).map((r, i) => `<tr><td>${i + 1}</td><td>${r.nama}</td><td>${r.rt || '—'}</td><td>${r.noHP || '—'}</td></tr>`).join('')}
          ${rows.length > 5 ? `<tr><td colspan="4" style="color:var(--text3);text-align:center">+ ${rows.length - 5} lainnya...</td></tr>` : ''}
        </tbody></table></div>
        <button class="btn btn-primary btn-block mt-3" onclick="Pages._doImportCSV(${JSON.stringify(rows).replace(/"/g, '&quot;')})">✅ Konfirmasi Import</button>`;
    };
    reader.readAsText(file);
  },

  _doImportCSV(rows) {
    rows.forEach(r => DB.insert('keluarga', { nama: r.nama, rt: r.rt, noHP: r.noHP, jumlahAnggota: parseInt(r.jumlahAnggota) || 0, alamat: r.alamat, pekerjaan: r.pekerjaan, penghasilan: r.penghasilan, status: 'aktif', ikutSampah: true, ikutPadaringan: true }));
    App.closeModal(); App.render('pendataan'); App.toast(`✅ ${rows.length} KK berhasil diimport`, 'success');
  },

  /* ============================================================
     Placeholder pages — handled elsewhere
     ============================================================ */
  iuran(type) { return Pages._notImpl(`Iuran ${type === 'sampah' ? 'Sampah' : 'Padaringan'}`); },
  setor() { return Pages._notImpl('Setor Sampah RT'); },
  pengeluaran() { return Pages._notImpl('Pengeluaran'); },
  sumbangan() { return Pages._notImpl('Sumbangan'); },
  aduan() { return Pages._notImpl('Aduan Warga'); },
  mpwa() { return Pages._notImpl('MPWA Broadcast'); },
  laporan() { return Pages._notImpl('Laporan'); },
  bukukas() { return Pages._notImpl('Buku Kas'); },

  _notImpl(name) {
    return `<div class="empty-state animate-in"><div class="empty-state-icon">🚧</div><div class="empty-state-title">${name}</div><div class="empty-state-sub">Halaman ini sedang diperbarui</div></div>`;
  }
};
