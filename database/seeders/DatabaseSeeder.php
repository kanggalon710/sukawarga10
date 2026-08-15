<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Akun bawaan dibuat HANYA bila belum ada.
        // Sengaja bukan updateOrCreate: DEPLOY.md menyuruh operator menjalankan
        // db:seed di produksi, dan updateOrCreate akan mengembalikan PIN ke nilai
        // bawaan setiap kali dijalankan — membatalkan PIN yang sudah diganti pengurus.
        $akunBawaan = [
            [
                'username' => 'admin',
                'user_id' => 'admin_id_01',
                'namaLengkap' => 'Administrator',
                'pin' => '463696',
                'level' => 'admin',
                'isDefault' => true,
            ],
            [
                'username' => 'jabnet',
                'user_id' => 'jabnet_super_01',
                'namaLengkap' => 'Jabnet Super Admin',
                'pin' => '463696',
                'level' => 'superadmin',
                'isDefault' => false,
            ],
        ];

        foreach ($akunBawaan as $akun) {
            $user = User::where('username', $akun['username'])->first();
            if ($user) {
                $this->command?->info("Akun {$akun['username']} sudah ada, dilewati.");
            } else {
                $user = User::create([
                    'user_id' => $akun['user_id'],
                    'username' => $akun['username'],
                    'namaLengkap' => $akun['namaLengkap'],
                    'pin' => bcrypt($akun['pin']),
                    'level' => $akun['level'],
                    'status' => 'aktif',
                    'isDefault' => $akun['isDefault'],
                ]);
                $this->command?->warn("Akun {$akun['username']} dibuat dengan PIN bawaan. GANTI setelah login pertama.");
            }

            $this->pasangPeranPlatform($user);
        }
    }

    /**
     * Akun bawaan adalah operator penuh sistem: super_admin di organisasi
     * platform (Phase E1). Idempoten - baris assignment hanya dibuat bila
     * belum ada, jadi aman diulang seperti pembuatan akunnya.
     */
    private function pasangPeranPlatform(User $user): void
    {
        $roleId = \App\Models\Role::where('slug', 'super_admin')->value('id');
        $platformId = \App\Models\Organization::where('slug', 'platform')->value('id');
        if (! $roleId || ! $platformId) {
            return; // migrasi E1/B1 belum jalan; fallback users.level tetap berlaku
        }

        \App\Models\UserRoleAssignment::firstOrCreate([
            'user_id' => $user->id, 'role_id' => $roleId, 'organization_id' => $platformId,
        ]);
    }
}
