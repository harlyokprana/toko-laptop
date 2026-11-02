<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserManagementController extends Controller
{
    public function create(): View
    {
        return view('users.create', [
            'roles' => Role::query()
                ->where('is_active', true)
                ->whereNotIn('name', ['PIMPINAN', 'PELANGGAN'])
                ->orderBy('name')
                ->get(['id', 'name', 'description']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNotIn('name', ['PIMPINAN', 'PELANGGAN'])),
            ],
            'status' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role_id' => $data['role_id'],
            'status' => $data['status'],
            'password' => Hash::make($data['password']),
        ]);

        return redirect()->route('module.users')->with('status', 'Pengguna berhasil ditambahkan.');
    }
}
