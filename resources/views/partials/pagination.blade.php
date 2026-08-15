{{--
    Navigasi halaman. Satu partial dipakai semua daftar berpaginasi.

    Tidak memakai $paginator->links() bawaan Laravel karena view bawaannya
    ditulis untuk Tailwind/Bootstrap, sedangkan project ini memakai
    public/css/styles.css (lihat AGENTS.md). Kelas tombol di bawah semuanya
    sudah ada di stylesheet itu, jadi tidak ada CSS baru dan tidak ada warna
    yang di-hardcode.

    Pemakaian: @include('partials.pagination', ['paginator' => $logs])
--}}
@if ($paginator->hasPages())
<nav aria-label="Navigasi halaman"
     style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; padding:16px 4px;">

    <p style="font-size:13px; color:var(--text3); margin:0;">
        Menampilkan {{ $paginator->firstItem() }} sampai {{ $paginator->lastItem() }}
        dari {{ $paginator->total() }} data
    </p>

    <ul style="display:flex; gap:8px; align-items:center; list-style:none; margin:0; padding:0;">
        <li>
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm btn-outline" aria-disabled="true"
                      style="min-height:44px; display:inline-flex; align-items:center; opacity:0.5;">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>&nbsp;Sebelumnya
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-sm btn-outline"
                   style="min-height:44px; display:inline-flex; align-items:center;">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>&nbsp;Sebelumnya
                </a>
            @endif
        </li>

        <li aria-current="page" style="font-size:13px; font-weight:600; padding:0 4px;">
            Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
        </li>

        <li>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-sm btn-outline"
                   style="min-height:44px; display:inline-flex; align-items:center;">
                    Berikutnya&nbsp;<i class="fas fa-chevron-right" aria-hidden="true"></i>
                </a>
            @else
                <span class="btn btn-sm btn-outline" aria-disabled="true"
                      style="min-height:44px; display:inline-flex; align-items:center; opacity:0.5;">
                    Berikutnya&nbsp;<i class="fas fa-chevron-right" aria-hidden="true"></i>
                </span>
            @endif
        </li>
    </ul>
</nav>
@endif
