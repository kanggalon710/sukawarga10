{{--
    Pita penanda instalasi demo (DEMO_MODE): semua data di situs ini fiktif.
    Gayanya ikut di sini karena halaman login tidak memuat styles.css.
    Warna amber (#78350F di atas #FEF3C7, kontras > 8:1) sengaja tetap,
    tidak ikut warna merek, supaya pita demo selalu terlihat sama.
--}}
@if(config('app.demo'))
    @once
        <style>
            .pita-demo { display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;
                padding: 8px 16px; background: #FEF3C7; color: #78350F; font-size: 13px; line-height: 1.4;
                text-align: center; border-bottom: 1px solid #FCD34D; position: relative; z-index: 300; }
        </style>
    @endonce
    <div class="pita-demo" role="note">
        <i class="fas fa-flask" aria-hidden="true"></i>
        <span><strong>Situs demo</strong> - semua data di sini fiktif dan WhatsApp tidak dikirim.</span>
    </div>
@endif
