# data-lokal

Tempat berkas kerja yang **berisi data pribadi warga**: lembar koreksi hasil audit,
ekspor sementara, potongan data untuk dicocokkan, catatan per-KK.

**Isi folder ini tidak pernah ikut ter-commit.** Repositori ini publik, dan yang
tersimpan di sini adalah nama, NIK, No. KK, alamat, serta nomor HP warga
sungguhan. Hanya `README.md` ini yang dilacak git, supaya keberadaan folder tetap
terlihat oleh siapa pun yang membuka repo.

Aturannya:

- Berkas apa pun boleh di sini (`.csv`, `.md`, `.xlsx`, `.json`), tidak perlu
  mengandalkan pola ekstensi di `.gitignore`.
- Jangan menyalin isinya ke `.ai/PROGRESS.md`, `.ai/TODO.md`, komentar kode, pesan
  commit, atau berkas tes. Rujuk saja nama berkasnya.
- Contoh di tes memakai nomor sintetis berkode wilayah `999999`, bukan identitas
  warga sungguhan. Lihat `tests/Feature/IdentitasWargaTest.php`.
- Folder ini tidak di-backup otomatis. Sumber kebenarannya tetap database
  produksi dan spreadsheet pendataan milik pengurus.
