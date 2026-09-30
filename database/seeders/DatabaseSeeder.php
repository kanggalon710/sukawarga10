<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Buat SATU admin platform pertama, dan hanya bila belum ada satu pun.
     *
     * Dulu seeder menulis PIN tetap untuk `admin` dan `jabnet` di repo publik,
     * jadi tiap instalasi baru punya super admin dengan PIN yang diketahui
     * siapa pun. Kini identitasnya dari ADMIN_USERNAME / ADMIN_PIN (lewat
     * config app.admin_awal, supaya tetap terbaca saat config di-cache), dan
     * bila ADMIN_PIN kosong dibuat PIN acak yang dicetak SEKALI ke konsol,
     * tidak pernah ke berkas atau log.
     *
     * Aman diulang: begitu ada super admin platform, seeder tidak menyentuh
     * akun apa pun, jadi `db:seed` di produksi tidak bisa mereset PIN.
     */
    public function run(): void
    {
        $roleId = Role::where('slug', 'super_admin')->value('id');
        $platformId = Organization::where('slug', 'platform')->value('id');
        if (! $roleId || ! $platformId) {
            throw new \RuntimeException('Migrasi peran/organisasi belum dijalankan. Jalankan php artisan migrate dulu.');
        }

        $sudahAda = UserRoleAssignment::where('role_id', $roleId)
            ->where('organization_id', $platformId)->exists();
        if ($sudahAda) {
            $this->command?->info('Admin platform sudah ada; seeder tidak mengubah akun apa pun.');

            return;
        }

        $username = trim((string) config('app.admin_awal.username'));
        if ($username === '') {
            throw new \RuntimeException('Isi ADMIN_USERNAME di .env untuk membuat admin platform pertama.');
        }
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{2,29}$/', $username)) {
            throw new \RuntimeException('ADMIN_USERNAME hanya boleh huruf kecil, angka, titik, strip, atau garis bawah (3-30 karakter).');
        }
        if (User::where('username', $username)->exists()) {
            // Tidak menaikkan akun yang sudah ada jadi super admin diam-diam.
            throw new \RuntimeException("Username {$username} sudah dipakai. Pilih ADMIN_USERNAME lain.");
        }

        $pin = trim((string) config('app.admin_awal.pin'));
        $dicetak = $pin === '';
        if ($dicetak) {
            do {
                $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (self::pinLemah($pin));
        } elseif (! preg_match('/^\d{6}$/', $pin) || self::pinLemah($pin)) {
            throw new \RuntimeException('ADMIN_PIN harus 6 angka dan tidak boleh berulang atau berurutan (mis. 000000, 123456).');
        }

        $user = User::create([
            'user_id' => 'usr_'.bin2hex(random_bytes(6)),
            'username' => $username,
            'namaLengkap' => 'Admin Platform',
            'pin' => Hash::make($pin),
            'level' => 'superadmin',
            'status' => 'aktif',
            // Akun default tidak bisa dinonaktifkan/dihapus lewat Manajemen
            // Akun: tanpa ini satu-satunya operator platform bisa terhapus.
            'isDefault' => true,
        ]);
        UserRoleAssignment::create([
            'user_id' => $user->id, 'role_id' => $roleId, 'organization_id' => $platformId,
        ]);

        $this->command?->info("Admin platform {$username} dibuat.");
        if ($dicetak) {
            $this->command?->warn("PIN awal: {$pin}");
            $this->command?->warn('Catat sekarang: PIN ini tidak disimpan di mana pun dan tidak akan ditampilkan lagi. Ganti setelah login pertama.');
        }
    }

    /** Semua digit sama, atau berurutan naik/turun. */
    private static function pinLemah(string $pin): bool
    {
        if (count(array_unique(str_split($pin))) === 1) {
            return true;
        }

        return str_contains('0123456789', $pin) || str_contains('9876543210', $pin);
    }
}
