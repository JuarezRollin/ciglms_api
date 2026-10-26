<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin']);
        Role::query()->firstOrCreate(['name' => 'glc staff']);
        Role::query()->firstOrCreate(['name' => 'glc admin']);
        Role::query()->firstOrCreate(['name' => 'encoder']);
        Role::query()->firstOrCreate(['name' => 'voucher creation']);
        Role::query()->firstOrCreate(['name' => 'reviewer']);
        Role::query()->firstOrCreate(['name' => 'box a reviewer']);
        Role::query()->firstOrCreate(['name' => 'outgoing']);
        Role::query()->firstOrCreate(['name' => 'glp admin']);
        Role::query()->firstOrCreate(['name' => 'compliance']);
        Role::query()->firstOrCreate(['name' => 'signatory']);
    }
}
