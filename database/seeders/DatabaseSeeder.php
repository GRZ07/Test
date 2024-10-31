<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Profile\Profile as ProfileProfile;
use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Role::factory()->count(5)->create();

        User::factory()
            ->count(10)
            ->has(Post::factory()->count(random_int(1, 10)))
            ->has(Item::factory()->count(random_int(1, 10)))
            ->has(ProfileProfile::factory()) // Establish one-to-one relationship with Profile
            ->create()
            ->each(function ($user) {
                // Attach roles to each user
                $roles = Role::inRandomOrder()->take(rand(1, 3))->pluck('id');
                $user->roles()->attach($roles);
            });

    }
}
