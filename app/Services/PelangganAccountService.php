<?php

namespace App\Services;

use App\Models\Pelanggan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PelangganAccountService
{
    public function ensureFor(Pelanggan $customer): User
    {
        $phone = trim((string) $customer->telepon);

        if ($phone === '') {
            throw ValidationException::withMessages([
                'telepon' => 'Nomor telepon wajib diisi untuk membuat akun pelanggan.',
            ]);
        }

        if ($customer->user) {
            return $customer->user;
        }

        $existingAccounts = User::query()
            ->where('phone', $phone)
            ->orWhere('email', $phone)
            ->get();

        if ($existingAccounts->count() > 1) {
            throw ValidationException::withMessages([
                'telepon' => 'Nomor telepon sudah digunakan oleh beberapa akun. Periksa data akun terlebih dahulu.',
            ]);
        }

        $existingAccount = $existingAccounts->first();
        if ($existingAccount) {
            if ($existingAccount->role?->name !== 'PELANGGAN') {
                throw ValidationException::withMessages([
                    'telepon' => 'Nomor telepon sudah digunakan oleh akun non-pelanggan.',
                ]);
            }

            if ($existingAccount->pelanggan && $existingAccount->pelanggan->isNot($customer)) {
                throw ValidationException::withMessages([
                    'telepon' => 'Nomor telepon sudah terhubung ke data pelanggan lain.',
                ]);
            }

            $customer->user()->associate($existingAccount);
            $customer->save();

            return $existingAccount;
        }

        $role = Role::query()->where('name', 'PELANGGAN')->where('is_active', true)->first();

        if (! $role) {
            throw new \RuntimeException('Role PELANGGAN aktif tidak ditemukan.');
        }

        $accountEmail = $customer->email;
        if (blank($accountEmail) || User::query()->where('email', $accountEmail)->exists()) {
            $accountEmail = 'customer-'.hash('sha256', $phone).'@accounts.prime.local';
        }

        $user = User::create([
            'name' => $customer->nama,
            'email' => $accountEmail,
            'phone' => $phone,
            'role_id' => $role->id,
            'status' => 'AKTIF',
            'password' => Hash::make($phone),
        ]);

        $customer->user()->associate($user);
        $customer->save();

        return $user;
    }
}
