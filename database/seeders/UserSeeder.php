<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $positions = ['Administrative Assistant I', 'Administrative Assistant II', 'Administrative Assistant III', 'Administrative Officer I', 'Administrative Officer II', 'Administrative Officer III'];

        $admin1 = User::query()->updateOrCreate(['email' => 'fassadicon@dswd.gov.ph'], [
            'name' => 'Frans Audrey Sadicon',
            'position' => fake()->randomElement($positions),
            'password' => bcrypt('password'),
            'office_id' => 1,
        ]);

        $admin2 = User::query()->updateOrCreate(['email' => 'lpdmolo@dswd.gov.ph'], [
            'name' => 'Lian Paul Molo',
            'position' => fake()->randomElement($positions),
            'password' => bcrypt('password'),
            'office_id' => 1,
        ]);

        $admin3 = User::query()->updateOrCreate(['email' => 'jrntalla@dswd.gov.ph'], [
            'name' => 'Jay Ray Talla',
            'position' => fake()->randomElement($positions),
            'password' => bcrypt('password'),
            'office_id' => 1,
        ]);

        $admin4 = User::query()->updateOrCreate(['email' => 'rtjuarez@dswd.gov.ph'], [
            'name' => 'Rollin Juarez',
            'position' => fake()->randomElement($positions),
            'password' => bcrypt('password'),
            'office_id' => 1,
        ]);

        $admin5 = User::query()->updateOrCreate(['email' => 'neoraya@dswd.gov.ph'], [
            'name' => 'Nicole Oraya',
            'position' => fake()->randomElement($positions),
            'password' => bcrypt('password'),
            'office_id' => 1,
        ]);

        $admin6 = User::query()->updateOrCreate(['email' => 'john.doe@dswd.gov.ph'], [
            'name' => 'John Doe',
            'position' => fake()->randomElement($positions),
            'password' => bcrypt('password'),
            'office_id' => 1,
        ]);

        $admin1->assignRole('admin');
        $admin2->assignRole('admin');
        $admin3->assignRole('admin');
        $admin4->assignRole('admin');
        $admin5->assignRole('admin');
        $admin6->assignRole('admin');

    }
}
