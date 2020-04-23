<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $regenerate = true;


        if ($regenerate) {
            $this->call(PermitSeeder::class);
            DB::table("zones")->insert(['name' => 'Zona 1']);
            DB::table("zones")->insert(['name' => 'Zona 2']);

            $root =  DB::table("persons")->insertGetId([
                'name' => 'ROOT',
                'status' => -999,
                'type'=> \App\Person::TYPE_USER,
            ]);

            $userRoot = \App\User::create([
                'person_id' => $root,
                'username' => 'root',
                'password' => bcrypt('root'),
            ]);

            $userRoot->assignRole(\App\Role::ROOT);

            if(config('app.debug')) {
                // personas
                $employ = DB::table("persons")->insertGetId([
                    'name' => 'employ',
                    'status' => 1,
                ]);

                $admin = DB::table("persons")->insertGetId([
                    'name' => 'ADMIN',
                    'status' => -999,
                    'type' => \App\Person::TYPE_USER,
                ]);

                // usuarios
                $employUser = \App\User::create([
                    'person_id' => $employ,
                    'username' => 'user',
                    'password' => bcrypt('1234'),
                    'zone_id' => 1,
                ]);

                $userAdmin = \App\User::create([
                    'person_id' => $admin,
                    'username' => 'admin',
                    'password' => bcrypt('admin'),
                ]);

                $employUser->assignRole(\App\Role::EMPLOY);
                $userAdmin->assignRole(\App\Role::ADMIN);
            }
        }
    }
}
