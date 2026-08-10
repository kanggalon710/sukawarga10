@extends('layouts.app')
@section('title', 'Pendaftaran Warga Baru')

@section('content')
<div class="top-header">
    <div class="page-title">
        <i class="fas fa-user-clock" style="color:var(--biru);"></i> Pendaftaran Warga Baru
    </div>
</div>

<div class="page-content">
    @if(session('success'))
        <div class="toast-message toast-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="toast-message toast-error"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <div class="card-title">Daftar Tunggu & Riwayat</div>
        </div>
        
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 140px;">Waktu Daftar</th>
                        <th>Nama Lengkap</th>
                        <th style="width: 160px;">NIK KTP</th>
                        <th style="width: 160px;">NIK Kartu Keluarga</th>
                        <th style="text-align:center; width: 60px;">RT</th>
                        <th style="width: 130px;">WhatsApp</th>
                        <th style="text-align:center; width: 100px;">Status</th>
                        <th style="text-align:right; width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendaftarans as $p)
                    <tr>
                        <td style="font-size: 13px; color: var(--text2);">{{ $p->created_at->format('d M Y H:i') }}</td>
                        <td style="font-weight: 600;">{{ $p->nama_lengkap }}</td>
                        <td>{{ $p->nik }}</td>
                        <td>{{ $p->no_kk }}</td>
                        <td style="text-align:center;"><span class="badge" style="background:var(--abu2);">{{ $p->rt }}</span></td>
                        <td>
                            @if($p->no_wa)
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $p->no_wa) }}" target="_blank" style="color:var(--hijau); text-decoration:none;">
                                <i class="fab fa-whatsapp"></i> {{ $p->no_wa }}
                            </a>
                            @else
                            <span style="color:var(--text3);">-</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            @if($p->status === 'pending')
                                <span class="badge" style="background:var(--kuning-pale); color:var(--kuning-dark);"><i class="fas fa-clock"></i> Pending</span>
                            @elseif($p->status === 'disetujui')
                                <span class="badge" style="background:var(--hijau-pale); color:var(--hijau);"><i class="fas fa-check"></i> Disetujui</span>
                            @else
                                <span class="badge" style="background:var(--merah-pale); color:var(--merah);"><i class="fas fa-times"></i> Ditolak</span>
                            @endif
                        </td>
                        <td style="text-align:right;">
                            @if($p->status === 'pending')
                            <div style="display:flex; justify-content:flex-end; gap:6px;">
                                <form action="{{ route('pendaftaran.approve', $p->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Setujui pendaftaran ini? Data KK dan Akun akan otomatis dibuat.');">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm" title="Setujui"><i class="fas fa-check"></i></button>
                                </form>
                                <button type="button" class="btn btn-outline btn-sm" style="color:var(--merah); border-color:var(--merah-pale);" title="Tolak" onclick="document.getElementById('rejectModal{{ $p->id }}').style.display='flex'"><i class="fas fa-times"></i></button>
                            </div>

                            <!-- Reject Modal -->
                            <div id="rejectModal{{ $p->id }}" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; align-items:center; justify-content:center; text-align:left;">
                                <div class="card" style="width:90%; max-width:400px; margin:0;">
                                    <div class="card-header"><div class="card-title" style="color:var(--merah);">Tolak Pendaftaran</div></div>
                                    <form action="{{ route('pendaftaran.reject', $p->id) }}" method="POST">
                                        @csrf
                                        <div style="margin-bottom:12px;">
                                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Alasan Penolakan</label>
                                            <textarea name="keterangan" required rows="3" style="width:100%; padding:10px; border:1px solid var(--abu2); border-radius:6px; font-family:inherit; font-size:14px;" placeholder="Contoh: NIK tidak valid / Data tidak lengkap"></textarea>
                                        </div>
                                        <div style="display:flex; gap:10px;">
                                            <button type="button" class="btn btn-outline" style="flex:1;" onclick="document.getElementById('rejectModal{{ $p->id }}').style.display='none'">Batal</button>
                                            <button type="submit" class="btn btn-primary" style="flex:1; background:var(--merah);"><i class="fas fa-times"></i> Tolak Pendaftaran</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            @elseif($p->status === 'ditolak')
                                <span style="font-size:11px; color:var(--text3);" title="{{ $p->keterangan }}">{{ \Illuminate\Support\Str::limit($p->keterangan, 20) }}</span>
                            @else
                                <span style="font-size:11px; color:var(--text3);"><i class="fas fa-check-circle text-success"></i> Selesai</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align:center; padding:30px; color:var(--text3);">Belum ada data pendaftaran warga.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
