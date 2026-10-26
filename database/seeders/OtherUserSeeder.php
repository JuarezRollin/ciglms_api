<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class OtherUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            // GLC STAFF
            [
                'email' => 'johndoe.reyes@dswd.gov.ph',
                'name' => 'John Reyes',
                'role' => 'glc staff',
            ],
            [
                'email' => 'juancarlos.yulopol@dswd.gov.ph',
                'name' => 'Juan Carlos Yulopol',
                'role' => 'glc staff',
            ],
            // GLC ADMIN
            [
                'email' => 'johndoeadmin.reyes@dswd.gov.ph',
                'name' => 'John Reyes',
                'role' => 'glc admin',
            ],
            [
                'email' => 'juancarlosadmin.yulopol@dswd.gov.ph',
                'name' => 'Juan Carlos Yulopol',
                'role' => 'glc admin',
            ],
            // ENCODER
            [
                'email' => 'john.reyes@dswd.gov.ph',
                'name' => 'John Reyes',
                'role' => 'encoder',
            ],
            [
                'email' => 'juan.yulopol@dswd.gov.ph',
                'name' => 'Juan Carlos Yulopol',
                'role' => 'encoder',
            ],
            // VOUCHER CREATION
            [
                'email' => 'rj.delacruz@dswd.gov.ph',
                'name' => 'Ray Jay Dela Cruz',
                'role' => 'voucher creation',
            ],
            [
                'email' => 'francis.magalona@dswd.gov.ph',
                'name' => 'Francis Magalona',
                'role' => 'voucher creation',
            ],

            // REVIEWER
            [
                'email' => 'lian.sanchez@dswd.gov.ph',
                'name' => 'Lian Sanchez',
                'role' => 'reviewer',
            ],
            [
                'email' => 'rollin.dimagiba@dswd.gov.ph',
                'name' => 'Rollin Dimagiba',
                'role' => 'reviewer',
            ],

            // OUTGOING
            [
                'email' => 'outgoing.master@dswd.gov.ph',
                'name' => 'Outgoing Master',
                'role' => 'outgoing',
            ],
            [
                'email' => 'outgoing.senior@dswd.gov.ph',
                'name' => 'Outgoing Senior',
                'role' => 'outgoing',
            ],

            // GLP ADMIN
            [
                'email' => 'jay.rambo@dswd.gov.ph',
                'name' => 'Jay Rambo',
                'role' => 'glp admin',
            ],
            [
                'email' => 'lian.blade@dswd.gov.ph',
                'name' => 'Lian Blade',
                'role' => 'glp admin',
            ],

            // BOX A REVIEWER
            [
                'email' => 'camilla.sanchez@dswd.gov.ph',
                'name' => 'Lian Sanchez',
                'role' => 'box a reviewer',
            ],
            [
                'email' => 'willie.chanco@dswd.gov.ph',
                'name' => 'Rollin Dimagiba',
                'role' => 'box a reviewer',
            ],

            // COMPLIANCE
            [
                'email' => 'mike.perez@dswd.gov.ph',
                'name' => 'Lian Sanchez',
                'role' => 'compliance',
            ],
            [
                'email' => 'wancho.sinco@dswd.gov.ph',
                'name' => 'Rollin Dimagiba',
                'role' => 'compliance',
            ],

            // SIGNATORY
            [
                'email' => 'esmorata@dswd.gov.ph',
                'name' => 'Edwin Morata',
                'role' => 'signatory',
            ],
            [
                'email' => 'lbbangalan@dswd.gov.ph',
                'name' => 'Lea Bangalan',
                'role' => 'signatory',
            ],
        ];

        $positions = ['Administrative Assistant I', 'Administrative Assistant II', 'Administrative Assistant III', 'Administrative Officer I', 'Administrative Officer II', 'Administrative Officer III'];

        foreach ($data as $value) {
            User::query()->updateOrCreate(['email' => $value['email']], [
                'name' => $value['name'],
                'position' => fake()->randomElement($positions),
                'password' => bcrypt('password'),
            ])->assignRole($value['role']);
        }
    }
}
