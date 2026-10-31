<?php

namespace Database\Seeders;

use App\Models\Pelanggan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoAccountsSeeder extends Seeder
{
    public const PASSWORD = 'password123';

    public function run(): void
    {
        $accounts = [
            ['Pimpinan Demo', 'pimpinan@example.com', 'PIMPINAN'],
            ['Manager Demo', 'manager@example.com', 'MANAGER'],
            ['Admin Demo', 'admin@example.com', 'ADMIN'],
            ['Kasir Demo', 'kasir@example.com', 'KASIR'],
            ['Teknisi Demo', 'teknisi@example.com', 'TEKNISI'],
            ['Pelanggan Demo', 'pelanggan@example.com', 'PELANGGAN'],
        ];

        foreach ($accounts as [$name, $email, $roleName]) {
            $role = Role::where('name', $roleName)->firstOrFail();

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(self::PASSWORD),
                    'role_id' => $role->id,
                    'status' => 'AKTIF',
                ],
            );

            if ($user->role_id !== $role->id) {
                $user->forceFill(['role_id' => $role->id])->save();
            }

            if ($roleName === 'PELANGGAN') {
                Pelanggan::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'kode_pelanggan' => 'DEMO-'.Str::upper(Str::random(8)),
                        'nama' => $user->name,
                        'email' => $user->email,
                    ],
                );
            }
        }
    }
}
