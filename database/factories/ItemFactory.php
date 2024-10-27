<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(), // Create and associate a new User

            'name' => $this->faker->word, // Generate a random word for the name
            'description' => $this->faker->sentence, // Generate a random sentence for the description
            // Optionally, you can add more fields here
        ];
    }
}

