<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetDataController extends Controller
{
    private const PROTECTED_TABLES = ['migrations', 'roles', 'permissions', 'role_permissions', 'users'];

    public function show(Request $request): View
    {
        $this->authorizeReset($request);

        return view('admin.reset-data', ['currentUser' => $request->user()]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->authorizeReset($request);
        $request->validate([
            'confirmation' => ['required', 'in:RESET ALL DATA'],
        ]);

        $connection = DB::connection();
        $database = $connection->getDatabaseName();
        $driver = $connection->getDriverName();
        $tables = [];
        foreach (Schema::getTableListing() as $listedTable) {
            if (str_contains($listedTable, '.')) {
                [$schema, $table] = explode('.', $listedTable, 2);
                if ($schema !== $database && ! ($driver === 'sqlite' && $schema === 'main')) {
                    continue;
                }
            } else {
                $table = $listedTable;
            }
            if (! \in_array($table, self::PROTECTED_TABLES, true)) {
                $tables[] = $table;
            }
        }
        if ($driver === 'sqlite') {
            $connection->statement('PRAGMA defer_foreign_keys = ON');
        } else {
            $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            $connection->transaction(function () use ($tables, $request): void {
                foreach ($tables as $table) {
                    DB::table($table)->delete();
                }

                DB::table('users')->where('id', '!=', $request->user()->id)->delete();
            });
        } finally {
            if ($driver === 'sqlite') {
                $connection->statement('PRAGMA defer_foreign_keys = OFF');
            } else {
                $connection->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        return redirect()->route('dashboard')->with('status', 'Data aplikasi berhasil direset. Role, permission, schema, dan akun pimpinan yang sedang digunakan tetap tersedia.');
    }

    private function authorizeReset(Request $request): void
    {
        abort_unless(app()->environment('local'), 404);
        abort_unless($request->user()?->role?->name === 'PIMPINAN' && $request->user()->hasPermission('manage_roles'), 403);
        abort_unless(
            \in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'sqlite'], true),
            501,
            'Reset data hanya didukung pada database MySQL/MariaDB atau SQLite lokal.',
        );
    }
}
